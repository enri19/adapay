@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="grid gap-3 md:grid-cols-3">
  <div class="badge">Login sebagai: {{ auth()->user()->name }}</div>
  <div class="badge">Clients aktif: {{ \App\Models\Client::where('is_active',1)->count() }}</div>
  <div class="badge">Payments (24h): {{ \App\Models\Payment::where('created_at','>=',now()->subDay())->count() }}</div>
</div>
<div class="mt-4">
  <p class="text-sm text-gray-600">Ringkasan cepat. Tambahkan kartu metrik lain sesuai kebutuhan.</p>
</div>
@endsection
