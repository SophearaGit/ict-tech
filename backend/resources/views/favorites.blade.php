@extends('layouts.app')

@section('title', 'My Favorites — ICTTech')

@section('content')
<div class="pt-20">
  <!-- PAGE HEADER -->
  <div class="mt-6">
    <div class="max-w-7xl mx-auto px-5 py-10">
      <h1 class="font-bold text-3xl sm:text-5xl text-gray-900 dark:text-gray-100 mb-1">My Favorites</h1>
      <p class="text-gray-400 text-md dark:text-white mt-3">
        Products you've saved — ready when you are.
      </p>
    </div>
  </div>

  <main class="max-w-7xl mx-auto px-5 py-6 pb-20" aria-label="Favorite products">
    @if ($products->isEmpty())
      <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="text-5xl mb-4" aria-hidden="true">🤍</div>
        <h3 class="text-gray-700 dark:text-gray-200 font-semibold text-lg mb-1">No favorites yet</h3>
        <p class="text-gray-400 text-sm mb-4">Tap the heart on any product to save it here.</p>
        <a href="{{ route('shop') }}" class="text-indigo-500 text-sm font-medium hover:underline">Browse the shop</a>
      </div>
    @else
      <p id="product-count" class="text-md text-gray-400 font-medium mb-5" role="status" aria-live="polite" aria-atomic="true"></p>
      <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5" aria-label="Products"></div>
    @endif
  </main>
</div>

<!-- FOOTER -->
<footer class="bg-black py-8">
  <div class="max-w-7xl mx-auto px-5 flex flex-col sm:flex-row items-center justify-between gap-4 text-md text-gray-400">
    <span class="font-display font-bold text-white">
    ICT<span class="text-indigo-500">Tech</span>
    </span>
    <span>© 2025 ICTTech. All rights reserved.</span>
    <nav aria-label="Footer links" class="flex gap-5">
      <a href="#" class="text-gray-300 hover:text-white transition-colors">About</a>
      <a href="{{ route('contact') }}" class="text-gray-300 hover:text-white transition-colors">Contact</a>
      <a href="{{ route('privacy') }}" class="text-gray-300 hover:text-white transition-colors">Privacy</a>
    </nav>
  </div>
</footer>
@endsection

@section('pre-scripts')
<script>
  window.__FAVORITE_IDS__ = @json($products->pluck('id'));
</script>
@endsection

@section('extra-scripts')
<script>
  const PRODUCTS = @json($products);
</script>
<script src="{{ asset('assets/js/shop.js') }}"></script>
@endsection
