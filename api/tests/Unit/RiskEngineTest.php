<?php

namespace Tests\Unit;

use App\Models\Reading;
use App\Models\Zone;
use App\Services\RiskEngine;
use PHPUnit\Framework\TestCase;

class RiskEngineTest extends TestCase
{
    private RiskEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RiskEngine;
    }

    /** Tezpur on the Brahmaputra: warning 64.50 m, danger 65.23 m. */
    private function zone(): Zone
    {
        return new Zone([
            'name' => 'Tezpur', 'slug' => 'tezpur', 'district' => 'Sonitpur',
            'latitude' => 26.6338, 'longitude' => 92.8,
            'warning_level_m' => 64.50, 'danger_level_m' => 65.23,
        ]);
    }

    private function reading(float $rain, float $water): Reading
    {
        return new Reading(['rainfall_mm' => $rain, 'water_level_m' => $water]);
    }

    public function test_safe_when_rainfall_and_water_level_are_normal(): void
    {
        $this->assertSame(RiskEngine::SAFE, $this->engine->assess($this->zone(), $this->reading(20.0, 60.0))['level']);
    }

    public function test_watch_on_heavy_rainfall_alone(): void
    {
        $this->assertSame(RiskEngine::WATCH, $this->engine->assess($this->zone(), $this->reading(70.0, 60.0))['level']);
    }

    public function test_warning_when_water_level_passes_the_warning_mark(): void
    {
        $this->assertSame(RiskEngine::WARNING, $this->engine->assess($this->zone(), $this->reading(10.0, 64.6))['level']);
    }

    public function test_severe_when_water_level_reaches_the_danger_mark(): void
    {
        $this->assertSame(RiskEngine::SEVERE, $this->engine->assess($this->zone(), $this->reading(10.0, 65.30))['level']);
    }

    public function test_escalates_when_heavy_rain_lands_on_an_already_high_river(): void
    {
        $result = $this->engine->assess($this->zone(), $this->reading(168.0, 64.8));

        $this->assertSame(RiskEngine::SEVERE, $result['level']);
        $this->assertContains('Escalated one step: heavy rainfall on an already high river.', $result['reasons']);
    }

    public function test_zone_with_no_reading_is_safe_but_flagged(): void
    {
        // Pre-load the relation as empty so the engine does not touch the database.
        $zone = $this->zone()->setRelation('latestReading', null);

        $result = $this->engine->assess($zone, null);

        $this->assertSame(RiskEngine::SAFE, $result['level']);
        $this->assertFalse($result['has_data']);
    }

    public function test_only_warning_and_above_alert_citizens(): void
    {
        $this->assertFalse($this->engine->isAlertable(RiskEngine::SAFE));
        $this->assertFalse($this->engine->isAlertable(RiskEngine::WATCH));
        $this->assertTrue($this->engine->isAlertable(RiskEngine::WARNING));
        $this->assertTrue($this->engine->isAlertable(RiskEngine::SEVERE));
    }
}
