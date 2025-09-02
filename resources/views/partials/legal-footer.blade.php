<footer class="mt-12 border-t bg-white/70 backdrop-blur">
  <div class="mx-auto max-w-6xl px-4 py-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div class="text-sm text-gray-600">
      © {{ date('Y') }} {{ config('app.name','AdaPay') }}. All rights reserved.
    </div>
    <nav class="flex items-center gap-3 text-sm">
      <a href="{{ route('agreement.show') }}" class="text-sky-700 hover:text-sky-900 underline">Perjanjian Layanan</a>
      <span class="text-gray-300">•</span>
      <a href="{{ route('privacy.show') }}" class="text-sky-700 hover:text-sky-900 underline">Kebijakan Privasi</a>
    </nav>
  </div>
</footer>
