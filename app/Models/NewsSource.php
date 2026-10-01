<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsSource extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'last_fetched_at' => 'datetime'];
    public function category() { return $this->belongsTo(Category::class); }
}

