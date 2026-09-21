@extends('layouts.app')

@section('title', 'ICT — Technology')

@section('extra-styles')
<style>
  /* Hides the YouTube embed's unremovable startup branding (title card +
     play controls) by keeping the iframe itself invisible for a few
     seconds, then fading it in once the branding has cleared. (An opaque
     cover layered on top of the iframe doesn't reliably work here -
     Chromium can composite a transformed iframe above sibling layers
     regardless of z-index, so fading the iframe's own opacity is the
     robust fix.) Re-triggered via JS on every loop restart, since YouTube
     replays the same branding each time a looped video restarts - see
     the hero-yt-player script below. */
  .hero-video-mask {
    opacity: 0;
    transition: opacity 1.2s ease;
  }
  .hero-video-mask.is-visible {
    opacity: 1;
  }
</style>
@endsection

@section('content')
  <!-- ─── PROMO BANNER (first-visit popup) ──────────────────────── -->
  <div id="promo-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <!-- Backdrop darkness: change the number after "/" (0-100 = 0%-100% opacity) -->
    <div id="promo-backdrop" class="absolute inset-0 bg-indigo-950/60"></div>
    <div class="relative bg-white dark:bg-neutral-800 rounded-3xl shadow-2xl max-w-sm w-full p-6 sm:p-7 border border-gray-100 dark:border-none">
      <button id="promo-close" aria-label="Close promotion"
        class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-neutral-700 dark:hover:text-white transition-colors">
        ✕
      </button>
      <span class="inline-flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold uppercase tracking-widest px-3 py-1.5 rounded-full mb-5">
        Limited offer
      </span>
      <div class="w-full h-40 rounded-2xl overflow-hidden mb-5 bg-gray-50 dark:bg-neutral-900">
        <img src="https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&q=80"
          alt="Samsung Galaxy A54 5G in awesome violet color" class="w-full h-full object-cover">
      </div>
      <h3 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-1">Galaxy A54 5G — 15% off</h3>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">This week only, while stocks last.</p>
      <div class="flex items-center gap-3">
        <a href="{{ route('shop') }}"
          class="flex-1 text-center bg-gray-900 hover:bg-indigo-600 text-white font-semibold px-5 py-3 rounded-2xl text-sm transition-colors">
          Shop the deal
        </a>
        <button id="promo-dismiss"
          class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-sm font-medium px-2 py-3 transition-colors">
          Not now
        </button>
      </div>
    </div>
  </div>

  <!-- ==================== HERO (video background) ========================= -->
  <section class="relative min-h-screen flex items-end overflow-hidden bg-neutral-900">
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none hero-video-mask">
      <iframe id="hero-yt-player"
        class="absolute top-1/2 left-1/2 w-[100vw] h-[56.25vw] min-h-[100vh] min-w-[177.78vh] -translate-x-1/2 -translate-y-1/2"
        src="https://www.youtube.com/embed/FUfGcZ092b0?autoplay=1&mute=1&loop=1&playlist=FUfGcZ092b0&controls=0&showinfo=0&modestbranding=1&rel=0&iv_load_policy=3&cc_load_policy=0&disablekb=1&playsinline=1&enablejsapi=1"
        title="Background video" frameborder="0"
        allow="autoplay; encrypted-media; picture-in-picture"
        allowfullscreen></iframe>
    </div>
    <div class="absolute inset-0 z-10 bg-gradient-to-t from-black/85 via-black/35 to-black/10"></div>

    <div class="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 pt-32 pb-16 lg:pb-24 w-full">
      <span class="fade-up inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm border border-white/20 text-white text-md font-semibold uppercase tracking-widest px-6 py-3 rounded-full mb-6">
        <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
        Elevate your lifestyle
      </span>
      <h1 class="fade-up font-bold text-4xl sm:text-6xl lg:text-7xl leading-none tracking-medium text-white mb-6" style="transition-delay:0.1s">
        MODERN <span class="italic font-light bg-gradient-to-r from-indigo-300 to-purple-300 bg-clip-text text-transparent">Tech</span><br />
        Lifestyle
      </h1>
      <p class="fade-up text-white/80 text-lg leading-relaxed max-w-lg mb-8" style="transition-delay:0.15s">Your next upgrade starts here.</p>
      <div class="fade-up flex flex-wrap items-center gap-5" style="transition-delay:0.2s">
        <a href="{{ route('shop') }}" class="bg-white hover:bg-indigo-50 text-gray-900 font-semibold px-8 py-4 rounded-2xl text-sm transition-all duration-200 shadow-lg hover:-translate-y-0.5 active:translate-y-0">
          Shop Now
        </a>
        <a href="#" class="border border-white/50 text-white font-semibold px-8 py-4 rounded-2xl text-sm transition-all duration-200 hover:bg-white/10 hover:-translate-y-0.5">
          Our Story
        </a>

        <!-- Social proof -->
        <div class="flex items-center gap-3 text-white/80">
          <div class="flex -space-x-2">
            <div class="w-8 h-8 rounded-full bg-indigo-200 border-2 border-white/80 flex items-center justify-center text-xs">😊</div>
            <div class="w-8 h-8 rounded-full bg-purple-200 border-2 border-white/80 flex items-center justify-center text-xs">😎</div>
            <div class="w-8 h-8 rounded-full bg-pink-200 border-2 border-white/80 flex items-center justify-center text-xs">🤩</div>
          </div>
          <span class="text-sm"><strong class="text-white">12,000+</strong> happy customers</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── BRANDS ──────────────────────────────────────────── -->
  <section class="bg-gray-50 dark:bg-neutral-900 dark:border-none backdrop-blur-lg  border-gray-100 dark:border-gray-800 py-10 transition-colors mt-10">
    <p class="text-center text-lg font-semibold uppercase tracking-widest text-gray-400 mb-15">Trusted brands in our catalog</p>
    <div class="marquee-wrapper py-2 mt-7">
      <div class="marquee-group">
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Apple</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Samsung</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Microsoft</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Sony</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">LG</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Huawei</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Google</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Asus</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Apple</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Samsung</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Microsoft</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Sony</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">LG</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Huawei</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Google</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Asus</div>
      </div>
      <div class="marquee-group" aria-hidden="true">
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Apple</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Samsung</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Microsoft</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Sony</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">LG</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Huawei</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Google</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Asus</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Apple</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Samsung</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Microsoft</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Sony</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">LG</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Huawei</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Google</div>
        <div class="glass-chip bg-white/80 dark:bg-neutral-800 border border-gray-100 dark:border-none rounded-2xl px-6 py-7 text-lg font-semibold text-gray-600 dark:text-gray-300 shadow-sm cursor-pointer transition-colors whitespace-nowrap hover:-translate-y-0.5">Asus</div>
      </div>
    </div>
  </section>

  <!-- ─── FEATURES ──────────────────────────────────────────── -->
  <section class=" bg-gray-50 dark:bg-neutral-900 py-16 px-4 transition-colors ">
    <div class="w-screen relative left-1/2 -translate-x-1/2 px-4 sm:px-6 text-center">
        <h2 class="text-4xl font-bold bg-clip-text text-gray-700 dark:text-white mb-10">
          Why ICT-Tech?
        </h2>
        <p class="text-gray-400 max-w-lg mx-auto text-lg mt-10">We offered an exclusive gadgets, delivered with trust.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-16 mb-10">
          <div class="glass-panel flex flex-col items-center bg-white/30 dark:bg-neutral-800 dark:border-none dark:shadow-2xl dark:backdrop-blur-xl backdrop-blur-xl border border-white/20 dark:border-none rounded-2xl shadow-lg p-6 hover:scale-105 transition-transform cursor-pointer">
            <div class="w-20 h-20 flex items-center justify-center rounded-full bg-gradient-to-r from-white to-gray-300 text-black text-3xl shadow-inner">🚚</div>
            <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">Fast Delivery</h3>
            <p class="text-gray-500 mt-2 text-lg">Orders ship within 24 hours. Free shipping on everything over $10.</p>
          </div>
          <div class="glass-panel flex flex-col items-center bg-white/30 dark:bg-neutral-800 dark:border-none dark:shadow-2xl dark:backdrop-blur-xl backdrop-blur-xl border border-white/20 dark:border-none rounded-xl shadow-lg p-6 hover:scale-105 transition-transform cursor-pointer">
              <div class="w-20 h-20 flex items-center justify-center rounded-full bg-gradient-to-r from-white to-gray-300 text-black text-3xl shadow-inner">📝</div>
              <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">Always Genuine</h3>
              <p class="text-gray-500 mt-2 text-lg">Every product is sourced directly from authorized distributors.</p>
          </div>
          <div class="glass-panel flex flex-col items-center bg-white/30 dark:bg-neutral-800 backdrop-blur-xl border border-white/20 dark:border-none dark:shadow-2xl dark:backdrop-blur-xl rounded-xl shadow-lg p-6 hover:scale-105 transition-transform cursor-pointer">
            <div class="w-20 h-20 flex items-center justify-center rounded-full bg-gradient-to-r from-white to-gray-300 text-black text-3xl shadow-inner">💵</div>
            <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">Hassle-Free Returns</h3>
            <p class="text-gray-500 mt-2 text-lg">30-day hassle-free returns, no questions asked.</p>
          </div>
          <div class="glass-panel flex flex-col items-center bg-white/30 dark:bg-neutral-800 backdrop-blur-xl border border-white/20 dark:border-none dark:shadow-2xl dark:backdrop-blur-xl rounded-xl shadow-lg p-6 hover:scale-105 transition-transform cursor-pointer ">
            <div class="w-20 h-20 flex items-center justify-center rounded-full bg-gradient-to-r from-white to-gray-300 text-black text-3xl shadow-inner">🏦</div>
            <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">Online Payment</h3>
            <p class="text-gray-500 mt-2 text-lg">24/7 Online Payment Access</p>
          </div>
        </div>
    </div>
  </section>

  <!-- ─── PROMO GRID ──────────────────────────────────────────── -->
  <section class="bg-gray-50 dark:bg-neutral-900 py-4 px-4 sm:px-6 transition-colors">

    <!-- ICT Watch - breaks out to the full viewport width, own left/right padding as its margin -->
    <div class="w-screen relative left-1/2 -translate-x-1/2 px-4 sm:px-6 mb-3">
      <div class="bg-black rounded-2xl overflow-hidden flex flex-col items-center text-center pt-12 pb-10 px-6">
        <div class="flex items-center justify-center mt-8 w-full max-w-3xl">
          <img src="{{ asset('assets/img/ict-watch-2.png') }}" alt="ICT Watch showing a readiness score"
            class="w-full h-auto rounded-2xl shadow-2xl">
        </div>
        <p class="text-gray-300 mt-8 text-lg">The most accurate heart rate sensing in a wearable.</p>
        <div class="flex items-center gap-5 mt-5">
          <a href="{{ route('shop') }}" class="bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Learn more</a>
          <a href="{{ route('shop') }}" class="border border-white/60 hover:bg-white/10 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Buy</a>
        </div>
      </div>
    </div>

    <!-- iPhone / Samsung row - breaks out to the full viewport width, own left/right padding as its margin -->
    <div class="w-screen relative left-1/2 -translate-x-1/2 px-4 sm:px-6 mb-3">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        <!-- iPhone 17 Pro Max -->
        <div class="relative bg-black rounded-2xl overflow-hidden flex flex-col items-center text-center pt-12 pb-8 px-6 min-h-[420px]">
          <h3 class="text-2xl font-bold text-white">iPhone 17 Pro Max</h3>
          <p class="text-gray-300 mt-2">Titanium strength. Pro camera system.</p>
          <div class="flex items-center gap-5 mt-5">
            <a href="{{ route('shop') }}" class="bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Learn more</a>
            <a href="{{ route('shop') }}" class="border border-white/60 hover:bg-white/10 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Buy</a>
          </div>
          <img src="{{ asset('assets/img/17 pro max.png') }}" alt="iPhone 17 Pro Max"
            class="mt-6 max-h-56 object-contain drop-shadow-2xl">
        </div>

        <!-- Samsung Galaxy S26 -->
        <div class="relative bg-black rounded-2xl overflow-hidden flex flex-col items-center text-center pt-12 pb-8 px-6 min-h-[420px]">
          <h3 class="text-2xl font-bold text-white">Samsung Galaxy S26</h3>
          <p class="text-gray-300 mt-2">AI that keeps up with you.</p>
          <div class="flex items-center gap-5 mt-5">
            <a href="{{ route('shop') }}" class="bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Learn more</a>
            <a href="{{ route('shop') }}" class="border border-white/60 hover:bg-white/10 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Buy</a>
          </div>
          <img src="{{ asset('assets/img/samsung-s26.png') }}" alt="Samsung Galaxy S26"
            class="mt-6 max-h-56 object-contain drop-shadow-2xl">
        </div>

      </div>
    </div>

    <!-- MacBook Pro / MacBook Neo row - breaks out to the full viewport width, own left/right padding as its margin -->
    <div class="w-screen relative left-1/2 -translate-x-1/2 px-4 sm:px-6 mb-3">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        <!-- MacBook Pro -->
        <div class="relative bg-neutral-100 dark:bg-neutral-800 rounded-2xl overflow-hidden flex flex-col items-center text-center pt-12 pb-8 px-6 min-h-[420px] transition-colors">
          <h3 class="text-2xl font-bold text-gray-900 dark:text-white">MacBook Pro 14"</h3>
          <p class="text-gray-500 dark:text-gray-400 mt-2">M5 Pro or M5 Max. A new level of Pro.</p>
          <div class="flex items-center gap-5 mt-5">
            <a href="{{ route('shop') }}" class="bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Learn more</a>
            <a href="{{ route('shop') }}" class="border border-gray-300 dark:border-white/30 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-900 dark:text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Buy</a>
          </div>
          <img src="{{ asset('assets/img/ict-macbook-pro.png') }}" alt="MacBook Pro 14-inch"
            class="mt-6 max-h-56 object-contain drop-shadow-2xl">
        </div>

        <!-- MacBook Neo -->
        <div class="relative bg-neutral-100 dark:bg-neutral-800 rounded-2xl overflow-hidden flex flex-col items-center text-center pt-12 pb-8 px-6 min-h-[420px] transition-colors">
          <h3 class="text-2xl font-bold text-gray-900 dark:text-white">MacBook Neo</h3>
          <p class="text-gray-500 dark:text-gray-400 mt-2">Strikingly thin. Strikingly colorful.</p>
          <div class="flex items-center gap-5 mt-5">
            <a href="{{ route('shop') }}" class="bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Learn more</a>
            <a href="{{ route('shop') }}" class="border border-gray-300 dark:border-white/30 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-900 dark:text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Buy</a>
          </div>
          <img src="{{ asset('assets/img/ict-macbook-neo.png') }}" alt="MacBook Neo"
            class="mt-6 max-h-56 object-contain drop-shadow-2xl">
        </div>

      </div>
    </div>

    <!-- ICT Upgrade / Carrier deals row - breaks out to the full viewport width, own left/right padding as its margin -->
    <div class="w-screen relative left-1/2 -translate-x-1/2 px-4 sm:px-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        <!-- ICT Upgrade -->
        <div class="relative bg-neutral-100 dark:bg-neutral-800 rounded-2xl overflow-hidden flex flex-col items-center text-center pt-10 pb-8 px-6 min-h-[420px] transition-colors">
          <h3 class="text-2xl font-bold text-gray-900 dark:text-white">ICT Upgrade</h3>
          <p class="text-gray-500 dark:text-gray-400 mt-2">Love it. Lease it. Upgrade it.</p>
          <a href="{{ route('shop') }}" class="mt-5 bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Learn more</a>
          <div class="relative flex items-center justify-center mt-8">
            <div class="absolute w-52 h-52 rounded-full bg-gradient-to-br from-red-400 via-pink-400 to-purple-500 opacity-50 blur-2xl"></div>
            <img src="{{ asset('assets/img/ict-upgrade-iphone.png') }}" alt="ICT Upgrade" class="relative max-h-56 object-contain drop-shadow-xl">
          </div>
        </div>

        <!-- Carrier deals -->
        <div class="bg-neutral-100 dark:bg-neutral-800 rounded-2xl flex flex-col items-center justify-center text-center pt-7 pb-6 px-6 min-h-[420px] transition-colors">
          <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Incredible Carrier Deals</h3>
          <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-xs">Explore deals that accept eligible trade-in devices in any condition.</p>
          <a href="{{ route('shop') }}" class="mt-5 bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold px-5 py-2 rounded-full transition-colors">Find your deal</a>
          <img src="{{ asset('assets/img/ict-airpods.png') }}" alt="ICT AirPods" class="mt-6 max-h-52 object-contain drop-shadow-xl">
        </div>

      </div>
    </div>
  </section>

  <!-- ─── CTA BANNER ──────────────────────────────────────────── -->
  <section class="bg-gray-50 dark:bg-neutral-900 py-20 text-center transition-colors">
    <div class="max-w-2xl mx-auto px-5">
      <h2 class="font-bold text-2xl sm:text-4xl text-neutral-600 dark:text-white mb-5">The best way to buy the products you love.</h2>
      <p class="text-gray-400 text-lg mb-8">Trusted tech, globally.</p>
      <a href="{{ route('shop') }}" class="inline-block bg-indigo-500 hover:bg-indigo-400 text-white font-semibold px-10 py-4 rounded-2xl text-base transition-colors shadow-lg hover:shadow-indigo-500/30">
        See our gadgets ? →
      </a>
    </div>
  </section>

  <!-- ─── FOOTER ──────────────────────────────────────────── -->
  <footer class="bg-gray-50 dark:bg-neutral-900 border-t dark:border-none border-gray-100 dark:border-gray-800 py-8 transition-colors">
    <div class="max-w-7xl mx-auto px-5 flex flex-col sm:flex-row items-center justify-between gap-4 text-lg text-gray-400">
      <span class="font-display font-bold text-gray-900 dark:text-white">ICT<span class="text-indigo-500">Tech</span></span>
      <span>© 2026 NovaDrop. All rights reserved.</span>
      <div class="flex gap-5">
        <a href="#" class="glass-icon-btn px-3 py-1.5 rounded-full border border-transparent hover:text-gray-700 dark:hover:text-white transition-colors">About</a>
        <a href="{{ route('contact') }}" class="glass-icon-btn px-3 py-1.5 rounded-full border border-transparent hover:text-gray-700 dark:hover:text-white transition-colors">Contact</a>
      </div>
    </div>
  </footer>
@endsection

@section('extra-scripts')
<script>
  // Hero fade-up content is always in view on load (it's not something you
  // scroll to reach), so reveal it immediately instead of gating it behind
  // a scroll IntersectionObserver - that risked a dim/invisible flash on
  // first paint if the intersection check didn't fire right away.
  requestAnimationFrame(() => {
    document.querySelectorAll('.fade-up').forEach(el => el.classList.add('visible'));
  });

  // Promo banner: show once per session, after a short delay
  (function () {
    const SEEN_KEY = 'novadrop_promo_seen';
    const modal = document.getElementById('promo-modal');
    const backdrop = document.getElementById('promo-backdrop');
    const closeBtn = document.getElementById('promo-close');
    const dismissBtn = document.getElementById('promo-dismiss');

    function openPromo() {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closePromo() {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      try { sessionStorage.setItem(SEEN_KEY, '1'); } catch (e) {}
    }

    let alreadySeen = false;
    try { alreadySeen = !!sessionStorage.getItem(SEEN_KEY); } catch (e) {}
    if (!alreadySeen) setTimeout(openPromo, 1200);

    [closeBtn, dismissBtn, backdrop].forEach(el => el.addEventListener('click', closePromo));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePromo();
    });
  })();

  // Hero background video: YouTube always replays its startup branding
  // (title card + play controls) whenever a looped video restarts, and
  // that can't be turned off via embed params - so we mask the iframe
  // ourselves. Rather than reacting to the loop after it happens (which
  // races against network/buffering jitter and can miss the exact
  // restart moment), this polls playback position and hides the video
  // proactively just BEFORE it's about to loop, then reveals it again a
  // few seconds after the restart - so the mask is always ahead of the
  // branding instead of chasing it.
  // Mobile OS "Now Playing" media controls (the pause/rewind/forward
  // overlay some browsers show for any autoplaying, looping video, even
  // muted) come from the Media Session API auto-detecting active
  // playback - not from our markup, so CSS can't touch it. Explicitly
  // telling the browser there's no session to show controls for is the
  // standard way to suppress it for a purely decorative background video.
  if ('mediaSession' in navigator) {
    navigator.mediaSession.metadata = null;
    navigator.mediaSession.playbackState = 'none';
    ['play', 'pause', 'seekbackward', 'seekforward', 'previoustrack', 'nexttrack', 'stop']
      .forEach(action => {
        try { navigator.mediaSession.setActionHandler(action, null); } catch (e) {}
      });
  }

  (function () {
    const mask = document.querySelector('.hero-video-mask');
    if (!mask) return;
    let heroPlayer = null;
    let revealTimer = null;
    let lastTime = 0;

    function hide() {
      clearTimeout(revealTimer);
      mask.classList.remove('is-visible');
    }

    function reveal(delay) {
      clearTimeout(revealTimer);
      mask.classList.remove('is-visible');
      revealTimer = setTimeout(() => mask.classList.add('is-visible'), delay);
    }

    function pollLoop() {
      if (!heroPlayer || typeof heroPlayer.getDuration !== 'function') return;
      let t, d;
      try {
        t = heroPlayer.getCurrentTime();
        d = heroPlayer.getDuration();
      } catch (e) { return; }
      if (!d) return;

      // About to loop: hide well before the restart so the branding
      // never has a chance to render visibly.
      if (d - t < 1.5) hide();

      // Just looped (playback position jumped backwards): start the
      // reveal countdown fresh.
      if (t < lastTime - 1) reveal(3500);

      lastTime = t;
    }

    // cc_load_policy=0 only stops captions being forced ON - if the viewer's
    // own YouTube account has captions enabled, YouTube shows them anyway,
    // and the captions module can reload itself (e.g. on every loop
    // restart). Unloading it every time it (re)announces itself via
    // onApiChange is the only reliable way to keep captions off.
    function killCaptions() {
      try { heroPlayer.unloadModule('captions'); } catch (e) {}
    }

    window.onYouTubeIframeAPIReady = function () {
      heroPlayer = new YT.Player('hero-yt-player', {
        events: {
          onReady: () => {
            killCaptions();
            reveal(150);
            setInterval(pollLoop, 250);
            setInterval(killCaptions, 1000);
          },
          onApiChange: killCaptions
        }
      });
    };

    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
  })();
</script>
@endsection
