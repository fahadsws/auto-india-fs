<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLog extends Model
{
    protected $guarded = [];
    protected $casts = ['sources' => 'array', 'cached' => 'boolean'];
}

