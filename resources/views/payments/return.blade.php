@extends('layouts.app')
@section('title', 'Status Pembayaran')

@section('content')
<div class="max-w-xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-3">Status Pembayaran</h1>
  @if(!$orderId)
    <div class="text-sm text-red-600">Order ID tidak ditemukan.</div>
  @else
    <p class="text-sm mb-4">Order ID: <strong>{{ $orderId }}</strong></p>

    @if($status === 'PAID')
      <div class="rounded border border-green-200 bg-green-50 p-3 mb-4">Pembayaran <strong>berhasil</strong>.</div>
      @if($creds)
        <div class="rounded border p-3">
          <h2 class="font-medium mb-2">Akun Hotspot Kamu</h2>
          <p>Username: <code>{{ $creds['u'] }}</code></p>
          <p>Password: <code>{{ $creds['p'] }}</code></p>
        </div>
      @else
        <div class="text-sm">Menyiapkan akun hotspot…</div>
        <script>setTimeout(()=>location.reload(),1500)</script>
      @endif
    @elseif($status === 'PENDING')
      <div class="rounded border border-yellow-200 bg-yellow-50 p-3 mb-4">Menunggu pembayaran…</div>
      <script>setTimeout(()=>location.reload(),1500)</script>
    @else
      <div class="rounded border p-3 mb-4">Status: {{ $status }}</div>
    @endif

    <div class="mt-4">
      <a class="text-blue-600 underline" href="{{ route('hotspot.order', ['orderId'=>$orderId]) }}">Kembali ke halaman order</a>
    </div>
  @endif
</div>
@endsection
