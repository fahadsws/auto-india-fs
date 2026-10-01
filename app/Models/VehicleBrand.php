<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class VehicleBrand extends Model { protected $guarded=[]; protected static function booted(){static::creating(function($m){$m->slug=Str::slug($m->name);});} }
