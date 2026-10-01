<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingSource extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'respect_robots' => 'boolean', 'mapping' => 'array', 'last_run_at' => 'datetime'];
}

