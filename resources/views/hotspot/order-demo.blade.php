@extends('layouts.app')
@section('title','Order Demo — AdaPay')

@push('head')
<style>
  .panel{border:1px solid #e5e7eb;border-radius:1rem;background:#fff}
  .panel-hd{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid #eef2f7}
  .panel-bd{padding:1rem 1.2rem}
  .badge{display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .55rem;border-radius:999px;border:1px solid #e5e7eb;background:#fff;font-size:.7rem;font-weight:600}
  .dot{width:.4rem;height:.4rem;border-radius:999px}
  .dot-pending{background:#f59e0b}.dot-paid{background:#10b981}.dot-expired{background:#ef4444}
  .kv{display:grid;grid-template-columns:140px 1fr;gap:.25rem .75rem}
  .qrbox{display:flex;align-items:center;justify-content:center;border:1px dashed #cfe1f9;background:#f8fbff;border-radius:.75rem;height:220px}
  .pm-card{display:flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:.6rem;background:#fff;padding:.5rem .7rem;cursor:pointer}
  .pm-card[aria-checked="true"]{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
  .pm-radio{position:absolute;opacity:0;width:0;height:0}
  .btn-sm{padding:.45rem .7rem;font-size:.9rem}
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto">
  <div class="panel">
    <div class="panel-hd">
      <div class="flex items-center gap-2">
        <h1 class="text-lg md:text-xl font-semibold">Order Demo</h1>
        <span class="badge">
          <span id="badgeDot" class="dot dot-pending"></span>
          <span id="badgeText">PENDING</span>
        </span>
      </div>
      <a href="{{ url('/hotspot') }}" class="btn btn--ghost btn-sm">Kembali ke Beli</a>
    </div>

    <div class="panel-bd">
      <div class="grid md:grid-cols-3 gap-4">
        <!-- Kiri: detail & aksi -->
        <div class="md:col-span-2 space-y-4">
          <div class="rounded-xl border bg-white p-4">
            <div class="text-sm text-gray-500">Order ID</div>
            <div class="font-semibold">{{ $order->order_id }}</div>
            <div class="kv mt-3 text-sm">
              <div class="text-gray-500">Client</div><div class="font-medium">{{ $order->client_id }}</div>
              <div class="text-gray-500">Nominal</div><div class="font-medium">{{ $order->currency }} {{ number_format($order->amount,0,',','.') }}</div>
              <div class="text-gray-500">Metode</div><div class="font-medium" id="methodText">{{ $order->payment_method }}</div>
              <div class="text-gray-500">Kadaluarsa</div><div class="font-medium"><span id="expTimer">—</span></div>
              <div class="text-gray-500">Ref. Provider</div><div class="font-medium">{{ $order->provider_ref }}</div>
            </div>
          </div>

          {{-- Aksi pembayaran (demo) --}}
          <div id="payBox" class="rounded-xl border bg-white p-4">
            <div class="font-semibold mb-2">Selesaikan Pembayaran (Demo)</div>

            {{-- pilih metode (demo) --}}
            <div class="flex flex-wrap gap-2 mb-3">
              <label class="pm-card" role="radio" aria-checked="true">
                <input class="pm-radio" type="radio" name="demo_method" value="QRIS" checked>
                <span>QRIS</span>
              </label>
              <label class="pm-card" role="radio" aria-checked="false">
                <input class="pm-radio" type="radio" name="demo_method" value="GoPay">
                <span>GoPay</span>
              </label>
              <label class="pm-card" role="radio" aria-checked="false">
                <input class="pm-radio" type="radio" name="demo_method" value="ShopeePay">
                <span>ShopeePay</span>
              </label>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
              <div class="qrbox">
                <div class="text-center text-sm text-gray-600">
                  <div class="font-semibold mb-1">QR / Deeplink Placeholder</div>
                  <div>Halaman ini hanya demo, tidak memproses pembayaran nyata.</div>
                </div>
              </div>
              <div class="space-y-2">
                <button id="btnMarkPaid" class="btn btn--primary w-full">Tandai Lunas (Simulasi)</button>
                <button id="btnSimError" class="btn w-full">Simulasikan Gangguan</button>
                <p class="text-xs text-gray-500">Gunakan tombol di atas untuk menunjukkan alur “PAID” & kasus error secara aman.</p>
              </div>
            </div>
          </div>

          {{-- Kredensial (muncul saat PAID) --}}
          <div id="credBox" class="rounded-xl border bg-slate-50 p-4 hidden">
            <div class="text-sm font-semibold text-slate-800">Kredensial Hotspot</div>
            <div class="mt-2 grid md:grid-cols-2 gap-3 text-sm">
              <div>
                <div class="text-gray-500">Username</div>
                <div class="font-mono">{{ $order->hotspot_user }}</div>
              </div>
              <div>
                <div class="text-gray-500">Password/Kode</div>
                <div class="font-mono" id="codeVal">{{ $order->hotspot_pass }}</div>
              </div>
            </div>
            <div class="mt-3">
              <button id="btnCopy" class="copy-btn" type="button">
                <svg class="ic" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12V1Zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H8V7h11v14Z"/></svg>
                Salin Kode
              </button>
              <span id="copyOk" class="hidden text-xs text-emerald-700 ml-2">Tersalin!</span>
            </div>
          </div>
        </div>

        <!-- Kanan: ringkasan -->
        <aside class="space-y-4">
          <div class="rounded-xl border bg-gradient-to-br from-white to-sky-50 p-4">
            <div class="font-semibold mb-1">Ringkasan</div>
            <div class="flex justify-between text-sm"><span>Voucher</span><span>Demo Paket</span></div>
            <div class="flex justify-between text-sm"><span>Metode</span><span id="sumMethod">QRIS</span></div>
            <hr class="my-2 border-blue-100">
            <div class="flex justify-between font-semibold"><span>Total</span><span>{{ $order->currency }} {{ number_format($order->amount,0,',','.') }}</span></div>
          </div>

          <div class="rounded-xl border bg-white p-4">
            <div class="font-semibold mb-1">Catatan</div>
            <ul class="list-disc ml-5 text-sm text-gray-600 space-y-1">
              <li>Halaman ini untuk demo UI/UX. Tidak terhubung ke gateway pembayaran.</li>
              <li>Tombol “Tandai Lunas” hanya mengubah tampilan menjadi status <em>PAID</em>.</li>
              <li>“Simulasikan Gangguan” menunjukkan bagaimana aplikasi merespons kegagalan kanal.</li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  var status = 'PENDING';
  var sec = {{ (int) $order->expires_in_sec }};
  var badgeText = document.getElementById('badgeText');
  var badgeDot  = document.getElementById('badgeDot');
  var expTimer  = document.getElementById('expTimer');
  var payBox    = document.getElementById('payBox');
  var credBox   = document.getElementById('credBox');
  var sumMethod = document.getElementById('sumMethod');
  var methodText= document.getElementById('methodText');

  // countdown
  function fmt(n){ return n<10 ? '0'+n : ''+n; }
  function tick(){
    if (status !== 'PENDING') return;
    sec = Math.max(0, sec-1);
    var m = Math.floor(sec/60), s = sec%60;
    expTimer.textContent = m+':'+fmt(s);
    if (sec === 0) setExpired();
  }
  setInterval(tick, 1000);
  tick();

  function setBadge(newStatus){
    status = newStatus;
    badgeText.textContent = newStatus;
    badgeDot.className = 'dot ' + (newStatus==='PAID' ? 'dot-paid' : newStatus==='EXPIRED' ? 'dot-expired' : 'dot-pending');
  }
  function setPaid(){
    setBadge('PAID');
    payBox.classList.add('hidden');
    credBox.classList.remove('hidden');
  }
  function setExpired(){
    setBadge('EXPIRED');
    payBox.classList.add('hidden');
  }

  // metode radio (demo)
  document.querySelectorAll('.pm-card').forEach(function(card){
    card.addEventListener('click', function(){
      document.querySelectorAll('.pm-card').forEach(function(c){
        c.setAttribute('aria-checked','false');
        var r = c.querySelector('.pm-radio'); if (r) r.checked = false;
      });
      card.setAttribute('aria-checked','true');
      var r = card.querySelector('.pm-radio'); if (r) r.checked = true;
      var val = r ? r.value : 'QRIS';
      sumMethod.textContent = val;
      methodText.textContent = val + ' (Demo)';
    });
  });

  // buttons
  document.getElementById('btnMarkPaid').addEventListener('click', function(){
    if (status === 'EXPIRED') return;
    setPaid();
  });

  document.getElementById('btnSimError').addEventListener('click', function(){
    if (status !== 'PENDING') return;
    // efek error singkat
    payBox.style.opacity = '.6';
    var old = methodText.textContent;
    methodText.textContent = old + ' — gangguan kanal';
    setTimeout(function(){
      payBox.style.opacity = '1';
      methodText.textContent = old + ' — retry OK';
    }, 1200);
  });

  // copy kode
  document.getElementById('btnCopy').addEventListener('click', function(){
    var txt = document.getElementById('codeVal').textContent || '';
    try {
      navigator.clipboard.writeText(txt).then(function(){
        document.getElementById('copyOk').classList.remove('hidden');
        setTimeout(function(){ document.getElementById('copyOk').classList.add('hidden'); }, 1200);
      });
    } catch(e){}
  });
})();
</script>
@endpush
