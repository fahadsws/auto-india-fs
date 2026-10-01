<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantSession extends Model
{
    protected $guarded = [];
    protected $casts = ['memory' => 'array', 'usage_date' => 'date', 'blocked_until' => 'datetime', 'last_message_at' => 'datetime'];

    public function lead() { return $this->belongsTo(Lead::class); }
}
