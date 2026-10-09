<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'district', 'river', 'latitude', 'longitude', 'population',
        'warning_level_m', 'danger_level_m',
        'inundation_map_path', 'inundation_map_url', 'inundation_map_updated_at',
        'sns_topic_arn',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'population' => 'integer',
        'warning_level_m' => 'float',
        'danger_level_m' => 'float',
        'inundation_map_updated_at' => 'datetime',
    ];

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->latestOfMany('recorded_at');
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
