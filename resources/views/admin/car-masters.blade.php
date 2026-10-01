@extends('admin.layout')
@section('title','Car Master')
@section('content')
<h4 class="mb-1">Car Master</h4><p class="text-muted mb-4">Manage the controlled values used across the car catalog.</p>
<ul class="nav nav-tabs mb-4"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#brands">Brands</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#body-types">Body types</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#fuels">Fuel</button></li></ul>
<div class="tab-content"><div class="tab-pane fade show active" id="brands">@include('admin.car-masters-list',['type'=>'brand','title'=>'Brands','items'=>$brands])</div><div class="tab-pane fade" id="body-types">@include('admin.car-masters-list',['type'=>'body_type','title'=>'Body types','items'=>$bodyTypes])</div><div class="tab-pane fade" id="fuels">@include('admin.car-masters-list',['type'=>'fuel','title'=>'Fuel types','items'=>$fuels])</div></div>
@endsection
