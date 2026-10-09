<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = ['zone_id', 'rainfall_mm', 'water_level_m', 'source', 'recorded_at'];

    protected $casts = [
        'rainfall_mm' => 'float',
        'water_level_m' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
