@extends('layouts.app')

@section('title', 'Admin Login — ICT-Tech')

@section('body-class', 'bg-gray-50 dark:bg-neutral-900')

@section('content')
<main class="min-h-screen bg-gray-50 grid-bg flex items-center justify-center px-4 dark:bg-neutral-900">
  <div class="card-in w-full max-w-md ">
    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="{{ route('home') }}" class="inline-flex items-center gap-2 mb-4">
        <img src="{{ asset('assets/img/ICT.jpg') }}" alt="ICT Professional Training Center logo" class="w-24 h-24 rounded-full object-cover shadow-sm"/>
      </a>
      <h1 class="font-display font-bold text-3xl text-gray-900 dark:text-slate-600 mb-5">Admin Portal</h1>
      <p class="text-gray-400 text-md mt-1 dark:text-white">Sign in to manage your store</p>
    </div>

    <!-- Card -->
    <div class="glass-panel bg-white/80 backdrop-blur-md rounded-3xl border border-gray-100 shadow-sm p-8">

      @if ($errors->any())
        <div class="bg-red-50 border border-red-100 text-red-600 text-sm rounded-2xl px-4 py-3 mb-5 flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('admin-login.attempt') }}" class="space-y-5">
        @csrf
        <!-- Email -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Email</label>
          <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <input type="email" name="email" placeholder="you@example.com" autocomplete="email"
              class="input-field w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 transition-all"/>
          </div>
        </div>

        <!-- Password -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Password</label>
          <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <input id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password"
              class="input-field w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 transition-all"/>
            <button type="button" onclick="togglePw()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
              <svg id="pw-eye" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>

        <!-- Submit -->
        <button type="submit"
          class="w-full bg-gray-900 hover:bg-indigo-600 text-white font-semibold py-3.5 rounded-2xl text-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0 flex items-center justify-center gap-2">
          Sign In
        </button>
      </form>
    </div>

    <p class="text-center text-md text-gray-400 mt-10">
      <a href="{{ route('home') }}" class="hover:text-gray-600 transition-colors mt-7 dark:hover:text-white">← Back to store</a>
    </p>
  </div>
</main>
@endsection

@section('extra-scripts')
<script>
  function togglePw() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }
</script>
@endsection
