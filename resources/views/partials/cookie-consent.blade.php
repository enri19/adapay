<div id="cookie-consent" class="fixed inset-x-0 bottom-3 z-50 hidden" role="dialog" aria-live="polite" aria-label="Cookie consent">
  <div class="mx-auto max-w-5xl px-3">
    <div class="rounded-xl border bg-white shadow-lg p-4 md:p-5 flex flex-col md:flex-row md:items-center gap-3">
      <div class="flex-1">
        <p class="text-sm text-gray-700">
          Kami menggunakan cookie esensial untuk menjalankan situs, dan cookie analitik opsional untuk meningkatkan layanan.
          Dengan menekan <strong>Terima</strong>, kamu setuju pada cookie esensial. Cookie analitik bersifat opsional.
        </p>
        <div class="mt-1 text-xs text-gray-500">
          Baca <a href="{{ url('/privacy') }}#cookies" class="underline text-sky-700">Kebijakan Privasi</a>.
        </div>
      </div>

      <div class="flex items-center gap-2">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 select-none">
          <input id="cookie-analytics-toggle" type="checkbox" class="h-4 w-4 border rounded">
          Aktifkan analytics
        </label>

        <button id="cookie-decline" type="button"
          class="inline-flex items-center rounded-lg border bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Tolak
        </button>

        <button id="cookie-accept" type="button" class="btn btn--primary">
          <span class="btn__label">Terima</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  try {
    var KEY = 'adapay_cookie_consent_v1'; // bump ke v2 kalau kamu ubah teks/kebijakan
    var banner = document.getElementById('cookie-consent');
    var acceptBtn = document.getElementById('cookie-accept');
    var declineBtn = document.getElementById('cookie-decline');
    var analyticsToggle = document.getElementById('cookie-analytics-toggle');

    function show(){ if(banner){ banner.classList.remove('hidden'); } }
    function hide(){ if(banner){ banner.classList.add('hidden'); } }

    function saveConsent(status){
      var payload = {
        status: status, // 'accepted' | 'declined'
        analytics: !!(analyticsToggle && analyticsToggle.checked),
        ts: new Date().toISOString()
      };
      try { localStorage.setItem(KEY, JSON.stringify(payload)); } catch(e){}
      // cookie 30 hari (opsional, biar server-side bisa baca jika perlu)
      try {
        var d = new Date(); d.setTime(d.getTime() + 30*24*60*60*1000);
        document.cookie = KEY + '=' + encodeURIComponent(JSON.stringify(payload)) +
                          '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
      } catch(e){}
      try { window.dispatchEvent(new CustomEvent('cookie-consent', { detail: payload })); } catch(e){}
    }

    function hasConsent(){
      try {
        var raw = localStorage.getItem(KEY);
        if(!raw) return false;
        var data = JSON.parse(raw);
        return data && (data.status === 'accepted' || data.status === 'declined');
      } catch(e){ return false; }
    }

    if(!hasConsent()) show();

    if(acceptBtn) acceptBtn.addEventListener('click', function(){
      saveConsent('accepted');
      // Bootstrap analytics di sini kalau perlu:
      // if (analyticsToggle && analyticsToggle.checked) initAnalytics();
      hide();
    });

    if(declineBtn) declineBtn.addEventListener('click', function(){
      if(analyticsToggle) analyticsToggle.checked = false;
      saveConsent('declined');
      hide();
    });

    // Auto-init analytics jika sebelumnya diizinkan
    try {
      var stored = localStorage.getItem(KEY);
      if(stored){
        var parsed = JSON.parse(stored);
        if(parsed && parsed.status === 'accepted' && parsed.analytics){
          // initAnalytics(); // pasang fungsi analytics kamu di sini
        }
      }
    } catch(e){}
  } catch(e){}
})();
</script>

<script>
// contoh placeholder
function initAnalytics(){
  // contoh Plausible:
  // var s = document.createElement('script');
  // s.defer = true; s.setAttribute('data-domain','adapay.example.com');
  // s.src = 'https://plausible.io/js/script.js';
  // document.head.appendChild(s);
}
</script>
