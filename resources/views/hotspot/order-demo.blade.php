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
      <div id="demoErr" class="hidden mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
        Gagal memuat status. Muat ulang halaman.
      </div>

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
document.addEventListener('DOMContentLoaded', function(){
  try {
    var status = 'PENDING';
    var sec = Number({{ (int) $order->expires_in_sec }}) || 0;

    var els = {
      badgeText : document.getElementById('badgeText'),
      badgeDot  : document.getElementById('badgeDot'),
      expTimer  : document.getElementById('expTimer'),
      payBox    : document.getElementById('payBox'),
      credBox   : document.getElementById('credBox'),
      sumMethod : document.getElementById('sumMethod'),
      methodText: document.getElementById('methodText'),
      btnPaid   : document.getElementById('btnMarkPaid'),
      btnErr    : document.getElementById('btnSimError'),
      btnCopy   : document.getElementById('btnCopy'),
      codeVal   : document.getElementById('codeVal'),
      copyOk    : document.getElementById('copyOk'),
      errBox    : document.getElementById('demoErr')
    };

    // Elemen wajib; kalau hilang, tampilkan error box & stop
    ['badgeText','badgeDot','expTimer','payBox','sumMethod','methodText'].forEach(function(k){
      if(!els[k]) throw new Error('Missing #' + k);
    });

    function fmt(n){ return (n<10?'0':'') + n; }
    function setBadge(s){
      status = s;
      els.badgeText.textContent = s;
      els.badgeDot.className = 'dot ' + (s==='PAID' ? 'dot-paid' : s==='EXPIRED' ? 'dot-expired' : 'dot-pending');
    }
    function setPaid(){
      setBadge('PAID');
      els.payBox.classList.add('hidden');
      if (els.credBox) els.credBox.classList.remove('hidden');
    }
    function setExpired(){
      setBadge('EXPIRED');
      els.payBox.classList.add('hidden');
    }

    // Countdown
    function tick(){
      if (status !== 'PENDING') return;
      sec = Math.max(0, sec - 1);
      els.expTimer.textContent = Math.floor(sec/60) + ':' + fmt(sec%60);
      if (sec === 0) setExpired();
    }
    tick();
    setInterval(tick, 1000);

    // Pilih metode (demo-only)
    document.querySelectorAll('.pm-card').forEach(function(card){
      card.addEventListener('click', function(){
        document.querySelectorAll('.pm-card').forEach(function(c){
          c.setAttribute('aria-checked','false');
          var r = c.querySelector('.pm-radio'); if (r) r.checked = false;
        });
        card.setAttribute('aria-checked','true');
        var r = card.querySelector('.pm-radio'); if (r) r.checked = true;
        var v = r ? r.value : 'QRIS';
        els.sumMethod.textContent = v;
        els.methodText.textContent = v + ' (Demo)';
      });
    });

    // Tombol
    if (els.btnPaid) els.btnPaid.addEventListener('click', function(){
      if (status !== 'EXPIRED') setPaid();
    });
    if (els.btnErr) els.btnErr.addEventListener('click', function(){
      if (status !== 'PENDING') return;
      var old = els.methodText.textContent;
      els.payBox.style.opacity = '.6';
      els.methodText.textContent = old + ' — gangguan kanal';
      setTimeout(function(){
        els.payBox.style.opacity = '1';
        els.methodText.textContent = old + ' — retry OK';
      }, 1200);
    });
    if (els.btnCopy) els.btnCopy.addEventListener('click', function(){
      var txt = (els.codeVal && els.codeVal.textContent) || '';
      if (!navigator.clipboard) return;
      navigator.clipboard.writeText(txt).then(function(){
        if (els.copyOk){
          els.copyOk.classList.remove('hidden');
          setTimeout(function(){ els.copyOk.classList.add('hidden'); }, 1200);
        }
      });
    });

  } catch (e) {
    var box = document.getElementById('demoErr');
    if (box) box.classList.remove('hidden');
    if (window.console && console.error) console.error('Order demo init failed:', e);
  }
});
</script>
@endpush
