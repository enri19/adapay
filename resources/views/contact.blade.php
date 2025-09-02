@extends('layouts.app')
@section('title','Hubungi Kami — AdaPay')

@section('content')
<section class="rounded-2xl border bg-gradient-to-br from-white to-sky-50 p-6 md:p-8">
  <div class="max-w-3xl">
    <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Hubungi Kami</h1>
    <p class="mt-2 text-gray-600">
      Ada pertanyaan seputar pembayaran, integrasi MikroTik, atau masalah transaksi?
      Kirim pesan lewat formulir ini, atau chat WhatsApp kami.
    </p>

    @if (session('ok'))
      <div class="mt-4 rounded-lg border bg-emerald-50 text-emerald-800 px-4 py-3">{{ session('ok') }}</div>
    @endif

    <form action="{{ route('contact.store') }}" method="post" class="mt-6 grid gap-4">
      @csrf
      {{-- Honeypot anti-spam --}}
      <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama</label>
          <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border px-3 py-2" />
          @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border px-3 py-2" />
          @error('email') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">No. WhatsApp (opsional)</label>
          <input type="text" name="hp" value="{{ old('hp') }}" class="mt-1 w-full rounded-lg border px-3 py-2" placeholder="62812xxxxxxx" />
          @error('hp') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Subjek</label>
          <input type="text" name="subject" value="{{ old('subject') }}" required class="mt-1 w-full rounded-lg border px-3 py-2" />
          @error('subject') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Pesan</label>
        <textarea name="message" rows="5" required class="mt-1 w-full rounded-lg border px-3 py-2">{{ old('message') }}</textarea>
        @error('message') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="flex items-center gap-3">
        <button class="btn btn--primary">
          <span class="btn__label">Kirim Pesan</span>
        </button>
        <a href="https://wa.me/62859106992437?text=Halo%20AdaPay%2C%20saya%20butuh%20bantuan."
           class="inline-flex items-center gap-2 rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
           target="_blank" rel="noopener">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3H4a1 1 0 0 0-1 1v16l4-4h13a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1Z"/></svg>
          WhatsApp
        </a>
      </div>

      <div class="grid md:grid-cols-3 gap-4 mt-6">
        <div class="rounded-xl border bg-white p-4">
          <div class="text-xs text-gray-500">Email Support</div>
          <div class="font-semibold">support@adanih.info</div>
        </div>
        <div class="rounded-xl border bg-white p-4">
          <div class="text-xs text-gray-500">Jam Layanan</div>
          <div class="font-semibold">Sen–Jum, 09.00–17.00 WIB</div>
        </div>
        <div class="rounded-xl border bg-white p-4">
          <div class="text-xs text-gray-500">SLA Target</div>
          <div class="font-semibold">Balas &lt; 4 jam kerja</div>
        </div>
      </div>
    </form>
  </div>
</section>
@endsection
