@extends('layouts.app')
@section('title','Lacak Pesanan — AdaPay')

@section('content')
<section class="rounded-2xl border bg-white p-6 md:p-8">
  <div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-sky-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a5 5 0 0 1 5 5c0 4.25-5 11-5 11S7 11.25 7 7a5 5 0 0 1 5-5Zm0 7.5A2.5 2.5 0 1 0 12 4a2.5 2.5 0 0 0 0 5.5ZM5 20.5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z"/></svg>
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Lacak Pesanan</h1>
    </div>

    <form action="{{ route('orders.lookup') }}" method="post" class="grid md:grid-cols-[1fr_auto] gap-3">
      @csrf
      <input type="text" name="order_id" value="{{ old('order_id', request('order_id')) }}" placeholder="Masukkan Order ID (mis. ORD-XXXX)"
             class="w-full rounded-lg border px-3 py-2" required />
      <button class="btn btn--primary"><span class="btn__label">Lacak</span></button>
      @error('order_id') <div class="text-xs text-red-600 mt-1 md:col-span-2">{{ $message }}</div> @enderror
    </form>

    @if ($order)
      <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-gradient-to-br from-sky-50 to-white p-4 md:col-span-2">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xs text-gray-500">Order ID</div>
              <div class="font-semibold">{{ $order->order_id }}</div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full border bg-white px-2.5 py-1 text-xs font-medium
              @class([
                'text-gray-700' => !in_array($order->status,['PAID','EXPIRED','CANCELED']),
                'text-emerald-700' => $order->status === 'PAID',
                'text-orange-700' => $order->status === 'PENDING',
                'text-red-700' => in_array($order->status,['EXPIRED','CANCELED']),
              ])">
              <span class="h-1.5 w-1.5 rounded-full
                @class([
                  'bg-gray-400' => !in_array($order->status,['PAID','EXPIRED','CANCELED','PENDING']),
                  'bg-emerald-500' => $order->status === 'PAID',
                  'bg-orange-500' => $order->status === 'PENDING',
                  'bg-red-500' => in_array($order->status,['EXPIRED','CANCELED']),
                ])"></span>
              {{ $order->status }}
            </span>
          </div>

          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt class="text-gray-500">Metode</dt>
              <dd class="font-medium">{{ $order->payment_method ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">Nominal</dt>
              <dd class="font-medium">
                @if(!is_null($order->amount))
                  {{ ($order->currency ?? 'IDR').' '.number_format($order->amount, 0, ',', '.') }}
                @else
                  —
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500">Dibuat</dt>
              <dd class="font-medium">
                @if(!empty($order->created_at))
                  {{ \Carbon\Carbon::parse($order->created_at)->timezone(config('app.timezone','Asia/Jakarta'))->format('d M Y H:i') }}
                @else
                  —
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500">Kadaluarsa</dt>
              <dd class="font-medium">
                @if(!empty($order->expires_at))
                  {{ \Carbon\Carbon::parse($order->expires_at)->timezone(config('app.timezone','Asia/Jakarta'))->format('d M Y H:i') }}
                @else
                  —
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500">Terbayar</dt>
              <dd class="font-medium">
                @if(!empty($order->paid_at))
                  {{ \Carbon\Carbon::parse($order->paid_at)->timezone(config('app.timezone','Asia/Jakarta'))->format('d M Y H:i:s') }}
                @else
                  —
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500">Ref. Provider</dt>
              <dd class="font-medium">{{ $order->provider_ref ?? '—' }}</dd>
            </div>
          </dl>

          {{-- Info pembeli, kalau tersedia --}}
          @if($order->buyer_name || $order->buyer_phone || $order->buyer_email)
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div>
                <dt class="text-gray-500">Pembeli</dt>
                <dd class="font-medium">{{ $order->buyer_name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Kontak</dt>
                <dd class="font-medium">
                  {{ $order->buyer_phone ?? '—' }} @if($order->buyer_email) • {{ $order->buyer_email }} @endif
                </dd>
              </div>
            </div>
          @endif

          {{-- Payment action (jika belum bayar dan masih berlaku) --}}
          @if (in_array($order->status, ['PENDING','WAITING']) && (!$order->expires_at || now()->lt(\Carbon\Carbon::parse($order->expires_at))))
            <div class="mt-6 grid gap-3">
              <div class="text-sm text-gray-700 font-medium">Selesaikan Pembayaran</div>
              <div class="flex flex-wrap items-center gap-2">
                @if ($order->qris_url)
                  <a href="{{ $order->qris_url }}" target="_blank" rel="noopener"
                     class="inline-flex items-center rounded-lg border bg-white px-3 py-2 text-sm hover:bg-gray-50">Lihat QRIS</a>
                @endif
                @if ($order->deeplink_url)
                  <a href="{{ $order->deeplink_url }}" target="_blank" rel="noopener"
                     class="inline-flex items-center rounded-lg border bg-white px-3 py-2 text-sm hover:bg-gray-50">Buka E-Wallet</a>
                @endif
              </div>
              <p class="text-xs text-gray-500">Jika link bermasalah, refresh halaman ini. Sistem mencoba <em>auto-retry</em> bila gateway error.</p>
            </div>
          @endif

          {{-- Credentials (jika sudah paid) --}}
          @if ($order->status === 'PAID')
            <div class="mt-6 rounded-xl border bg-slate-50 p-4">
              <div class="text-sm font-semibold text-slate-800">Kredensial Hotspot</div>
              <div class="mt-2 grid md:grid-cols-2 gap-3 text-sm">
                <div>
                  <div class="text-gray-500">Username</div>
                  <div class="font-mono">{{ $order->hotspot_username ?? '—' }}</div>
                </div>
                <div>
                  <div class="text-gray-500">Password/Kode</div>
                  <div class="font-mono">{{ $order->hotspot_password ?? '—' }}</div>
                </div>
              </div>
              <p class="mt-2 text-xs text-gray-500">Catatan: jika auto-login belum aktif, pakai username & password di atas di portal hotspot.</p>
            </div>
          @endif
        </div>

        {{-- Sampingan tips/faq --}}
        <aside class="rounded-xl border bg-white p-4">
          <div class="font-semibold mb-2">Bantuan Cepat</div>
          <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1">
            <li>Order tidak ditemukan? Cek ejaan Order ID.</li>
            <li>Status masih <em>PENDING</em>? Tunggu 1–2 menit lalu refresh.</li>
            <li>Sudah bayar tapi belum aktif? Hubungi support sambil kirim bukti bayar.</li>
          </ul>
          <a href="{{ url('/contact') }}" class="mt-3 inline-flex text-xs text-sky-700 hover:text-sky-900 underline">Hubungi Support</a>
        </aside>
      </div>
    @endif
  </div>
</section>
@endsection
