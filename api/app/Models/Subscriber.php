<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = ['zone_id', 'email', 'phone', 'sns_subscription_arn', 'sns_status'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
