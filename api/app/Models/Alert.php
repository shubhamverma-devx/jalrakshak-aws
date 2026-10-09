<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id', 'risk_level', 'message', 'recipients_count', 'channel',
        'sns_message_id', 'delivery_status', 'delivery_note', 'triggered_by',
    ];

    protected $casts = ['recipients_count' => 'integer'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
