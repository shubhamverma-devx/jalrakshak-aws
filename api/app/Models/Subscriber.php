<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscriber extends Model
{
    protected $fillable = ['village_id', 'email', 'phone', 'sns_subscription_arn', 'sns_status'];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** Only confirmed addresses actually receive an SNS publish. */
    public function scopeConfirmed($query)
    {
        return $query->where('sns_status', 'confirmed');
    }
}
