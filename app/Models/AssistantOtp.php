<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantOtp extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];
}
