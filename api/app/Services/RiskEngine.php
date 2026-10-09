<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\Zone;

/**
 * Rule based flood risk engine.
 *
 * Deliberately not machine learning. Every level an officer sees can be traced
 * back to a published threshold, which is what a district control room needs.
 *
 * Rainfall bands follow the India Meteorological Department 24 hour categories.
 * Water level bands follow the Central Water Commission idea of a per station
 * warning level and danger level, stored on each zone.
 */
class RiskEngine
{
    public const SAFE = 'Safe';
    public const WATCH = 'Watch';
    public const WARNING = 'Warning';
    public const SEVERE = 'Severe';

    /** Score to label. */
    public const LEVELS = [0 => self::SAFE, 1 => self::WATCH, 2 => self::WARNING, 3 => self::SEVERE];

    // IMD 24 hour rainfall categories, in mm.
    public const RAIN_HEAVY = 64.5;            // heavy
    public const RAIN_VERY_HEAVY = 115.6;      // very heavy
    public const RAIN_EXTREMELY_HEAVY = 204.5; // extremely heavy

    /**
     * Assess a zone from a reading. Returns the level plus the reasons behind it.
     *
     * @return array{level: string, score: int, rainfall_score: int, water_score: int, reasons: array<int, string>, rainfall_mm: float|null, water_level_m: float|null, has_data: bool}
     */
    public function assess(Zone $zone, ?Reading $reading = null): array
    {
        $reading ??= $zone->latestReading;

        if (! $reading) {
            return [
                'level' => self::SAFE,
                'score' => 0,
                'rainfall_score' => 0,
                'water_score' => 0,
                'reasons' => ['No sensor reading yet for this zone.'],
                'rainfall_mm' => null,
                'water_level_m' => null,
                'has_data' => false,
            ];
        }

        $rainfall = (float) $reading->rainfall_mm;
        $water = (float) $reading->water_level_m;
        $warning = (float) $zone->warning_level_m;
        $danger = (float) $zone->danger_level_m;

        $reasons = [];

        // Rule 1: rainfall in the last 24 hours.
        if ($rainfall >= self::RAIN_EXTREMELY_HEAVY) {
            $rainScore = 3;
            $reasons[] = sprintf('Extremely heavy rainfall: %.1f mm in 24h (over %.1f mm).', $rainfall, self::RAIN_EXTREMELY_HEAVY);
        } elseif ($rainfall >= self::RAIN_VERY_HEAVY) {
            $rainScore = 2;
            $reasons[] = sprintf('Very heavy rainfall: %.1f mm in 24h (over %.1f mm).', $rainfall, self::RAIN_VERY_HEAVY);
        } elseif ($rainfall >= self::RAIN_HEAVY) {
            $rainScore = 1;
            $reasons[] = sprintf('Heavy rainfall: %.1f mm in 24h (over %.1f mm).', $rainfall, self::RAIN_HEAVY);
        } else {
            $rainScore = 0;
            $reasons[] = sprintf('Rainfall normal: %.1f mm in 24h (under %.1f mm).', $rainfall, self::RAIN_HEAVY);
        }

        // Rule 2: water level against this zone's own warning and danger marks.
        if ($water >= $danger) {
            $waterScore = 3;
            $reasons[] = sprintf('Water level %.2f m is at or above the danger level of %.2f m.', $water, $danger);
        } elseif ($water >= $warning) {
            $waterScore = 2;
            $reasons[] = sprintf('Water level %.2f m is above the warning level of %.2f m.', $water, $warning);
        } elseif ($water >= $warning - 1.0) {
            $waterScore = 1;
            $reasons[] = sprintf('Water level %.2f m is within 1 m of the warning level of %.2f m.', $water, $warning);
        } else {
            $waterScore = 0;
            $reasons[] = sprintf('Water level %.2f m is well below the warning level of %.2f m.', $water, $warning);
        }

        // Rule 3: the worst single signal sets the floor.
        $score = max($rainScore, $waterScore);

        // Rule 4: heavy rain on an already high river escalates one step.
        if ($rainScore >= 2 && $waterScore >= 2 && $score < 3) {
            $score++;
            $reasons[] = 'Escalated one step: heavy rainfall on an already high river.';
        }

        return [
            'level' => self::LEVELS[$score],
            'score' => $score,
            'rainfall_score' => $rainScore,
            'water_score' => $waterScore,
            'reasons' => $reasons,
            'rainfall_mm' => $rainfall,
            'water_level_m' => $water,
            'has_data' => true,
        ];
    }

    /** Levels at or above which citizens should be alerted. */
    public function isAlertable(string $level): bool
    {
        return in_array($level, [self::WARNING, self::SEVERE], true);
    }

    /** Plain language advice shown to citizens for a level. */
    public function advice(string $level): string
    {
        return match ($level) {
            self::SEVERE => 'Move to the nearest relief camp or higher ground now. Do not cross flooded roads.',
            self::WARNING => 'Prepare to move. Keep documents, medicines and a torch ready. Avoid the riverbank.',
            self::WATCH => 'Stay alert and follow local announcements. Check on elderly neighbours.',
            default => 'No flood threat right now. Normal activity is safe.',
        };
    }

    /** The alert text published to Amazon SNS. */
    public function alertMessage(Zone $zone, array $assessment, ?string $zoneUrl = null): string
    {
        $lines = [
            sprintf('JALRAKSHAK FLOOD %s: %s, %s district.', strtoupper($assessment['level']), $zone->name, $zone->district),
            '',
            sprintf('Rainfall (24h): %.1f mm', $assessment['rainfall_mm'] ?? 0),
            sprintf('Water level: %.2f m (danger level %.2f m)', $assessment['water_level_m'] ?? 0, $zone->danger_level_m),
            '',
            'Why: '.implode(' ', $assessment['reasons']),
            '',
            'What to do: '.$this->advice($assessment['level']),
        ];

        if ($zoneUrl) {
            $lines[] = '';
            $lines[] = 'Live status and inundation map: '.$zoneUrl;
        }

        $lines[] = '';
        $lines[] = 'Issued by the JalRakshak district control room. Do not reply to this message.';

        return implode("\n", $lines);
    }
}
