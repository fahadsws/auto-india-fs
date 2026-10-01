<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $guarded = [];
    public function listing() { return $this->belongsTo(Listing::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}

