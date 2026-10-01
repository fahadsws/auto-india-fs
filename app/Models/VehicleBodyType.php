<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class VehicleBodyType extends Model { protected $guarded=[]; public function scopeOfType($q,string $type){return $q->where('vehicle_type',$type);} protected static function booted(){static::creating(function($m){$m->slug=Str::slug($m->name);});} }
