@extends('layouts.app')

@section('title', 'Messages — ICTTech Admin')

@section('body-class', 'bg-gray-50 dark:bg-neutral-800')

@section('content')
<main class="min-h-screen pt-28 pb-16 px-5 max-w-5xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="font-display font-bold text-2xl text-gray-900 dark:text-white">Contact Messages</h1>
    <a href="{{ route('admin-view') }}" class="text-sm text-indigo-500 hover:underline">← Back to dashboard</a>
  </div>

  <div class="glass-panel bg-white dark:bg-neutral-700 rounded-2xl border border-gray-100 dark:border-none shadow-sm overflow-hidden">
    @if ($messages->isEmpty())
      <div class="text-center py-16">
        <div class="text-4xl mb-3">📭</div>
        <p class="text-gray-500 dark:text-white font-medium">No messages yet</p>
        <p class="text-gray-400 dark:text-gray-300 text-sm mt-1">Submissions from the contact form will show up here.</p>
      </div>
    @else
      <div class="divide-y divide-gray-100 dark:divide-neutral-600">
        @foreach ($messages as $message)
          <div class="px-6 py-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1.5">
              <p class="font-semibold text-gray-900 dark:text-white">{{ $message->name }}
                <span class="font-normal text-gray-400 text-sm">&lt;{{ $message->email }}&gt;</span>
              </p>
              <span class="text-xs text-gray-400">{{ $message->created_at->format('M j, Y g:ia') }}</span>
            </div>
            @if ($message->subject)
              <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1.5">{{ $message->subject }}</p>
            @endif
            <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $message->message }}</p>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</main>
@endsection
