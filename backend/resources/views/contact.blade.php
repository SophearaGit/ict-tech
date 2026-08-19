@extends('layouts.app')

@section('title', 'Contact — NovaDrop')

@section('content')
<div class="pt-20">
  <!-- HERO -->
  <section class="py-14 text-center">
    <div class="max-w-2xl mx-auto px-5">
      <span class="inline-block text-md font-sbold uppercase tracking-widest text-indigo-500 mb-3 dark:text-slate-600">Get in Touch</span>
      <h1 class="font-extrabold text-3xl sm:text-5xl text-gray-900 mb-5 tracking-tight dark:text-white">We'd love to hear<br />from you.</h1>
      <p class="text-gray-400 text-base">Questions about a spec of tech, an order, We reply within 24 hours.</p>
    </div>
  </section>

  <!-- MAIN -->
  <section class="max-w-5xl mx-auto px-5 py-16 grid md:grid-cols-5 gap-10">
    <!-- Left info -->
    <div class="md:col-span-2 fade-up">
      <h2 class="font-display font-bold text-xl text-gray-900 mb-6 dark:text-slate-600">Contact Info</h2>
      <div class="space-y-5">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-lg flex-shrink-0">📍</div>
          <div>
            <p class="font-semibold text-md text-gray-800 dark:text-white">Location</p>
            <a href="https://maps.app.goo.gl/3568UAgzxBwvwL9S7" target="_blank">
              <span class="contact-text text-gray-500">
                ផ្ទះលេខ 240B ផ្លូវ 132 ភូមិ06 សង្កាត់ទឹកល្អក់ទី01 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ
              </span>
            </a>
          </div>
        </div>
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-lg flex-shrink-0">📧</div>
          <div>
            <p class="font-semibold text-md text-gray-800 dark:text-white">Email</p>
            <a href="mailto:hello@novadrop.com" class="text-indigo-500 text-sm hover:underline mt-0.5 block">ICTCenter@gmail.com</a>
          </div>
        </div>
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-lg flex-shrink-0">📱</div>
          <div>
            <p class="font-semibold text-md text-gray-800 dark:text-white mb-2">Telegram</p>
            <a href="#" class="text-indigo-500 text-sm hover:underline mt-0.5 block">
              <ul>
                <li>092 702 175</li>
                <li>096 287 5270</li>
              </ul>
            </a>
          </div>
        </div>
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-lg flex-shrink-0">⏰</div>
          <div>
            <p class="font-semibold text-md text-gray-800 dark:text-white">Hours</p>
            <p class="text-gray-400 text-sm mt-0.5">Mon – Sat: 9am – 6pm<br />Sunday: Closed</p>
          </div>
        </div>
      </div>

      <!-- FAQ teaser -->
      <div class="mt-8 bg-indigo-50 rounded-2xl p-5 border border-indigo-100">
        <h3 class="font-display font-bold text-gray-900 text-md mb-3">Quick Questions</h3>
        <div class="space-y-3 text-sm text-gray-500 dark:text-gray-600">
          <details class="group">
            <summary class="cursor-pointer font-medium text-gray-700 group-open:text-indigo-600 transition-colors">How long does shipping take?</summary>
            <p class="mt-1 text-gray-400 text-xs leading-relaxed pl-2 dark:text-gray-600">Phnom Penh orders: 1 days. Provinces: 2-4 days. International: 5–14 days.</p>
          </details>
          <details class="group">
            <summary class="cursor-pointer font-medium text-gray-700 group-open:text-indigo-600 transition-colors">Can I return my order?</summary>
            <p class="mt-1 text-gray-400 text-xs leading-relaxed pl-2 dark:text-gray-600">Yes, 30-day hassle-free returns on unworn items in original packaging.</p>
          </details>
          <details class="group">
            <summary class="cursor-pointer font-medium text-gray-700 group-open:text-indigo-600 transition-colors">Are the products authentic?</summary>
            <p class="mt-1 text-gray-400 text-xs leading-relaxed pl-2 dark:text-gray-600">100%. We source directly from authorized distributors only.</p>
          </details>
        </div>
      </div>
    </div>

    <!-- Right form -->
    <div class="md:col-span-3 fade-up" style="transition-delay:0.1s">
      <div class="glass-panel bg-white/70 backdrop-blur-md rounded-3xl border border-gray-100 shadow-sm p-8">
        <h2 class="font-display font-bold text-xl text-gray-900 mb-6">Send a Message</h2>

        <div id="form-success" class="hidden bg-green-50 border border-green-100 rounded-2xl p-6 text-center mb-6">
          <div class="text-4xl mb-2">✅</div>
          <h3 class="font-display font-bold text-gray-900 mb-1">Message Sent!</h3>
          <p class="text-gray-500 text-sm">We'll get back to you within 24 hours.</p>
        </div>

        <form id="contact-form" onsubmit="handleSubmit(event)" class="space-y-5" novalidate>
          <!-- Name + Email row -->
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Full Name *</label>
              <input id="field-name" type="text" name="name" placeholder="Your name"
                class="form-input w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 transition-all" />
              <p class="error-msg text-red-500 text-xs mt-1 hidden" id="err-name">Please enter your name.</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Email *</label>
              <input id="field-email" type="email" name="email" placeholder="you@email.com"
                class="form-input w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 transition-all" />
              <p class="error-msg text-red-500 text-xs mt-1 hidden" id="err-email">Please enter a valid email.</p>
            </div>
          </div>

          <!-- Subject -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Subject</label>
            <select name="subject" class="form-input w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 transition-all cursor-pointer">
              <option value="">Select a topic…</option>
              <option value="order">Order Inquiry</option>
              <option value="return">Return / Refund</option>
              <option value="product">Product Question</option>
              <option value="wholesale">Wholesale / Partnership</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Message -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Message *</label>
            <textarea id="field-message" name="message" rows="5" placeholder="Tell us how we can help…"
              class="form-input w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 transition-all resize-none"></textarea>
            <p class="error-msg text-red-500 text-xs mt-1 hidden" id="err-message">Please enter a message.</p>
          </div>

          <!-- Submit -->
          <button type="submit" id="submit-btn"
            class="w-full bg-gray-900 hover:bg-indigo-600 text-white font-semibold py-4 rounded-2xl text-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0 flex items-center justify-center gap-2">
            <span id="btn-text">Send Message</span>
            <svg id="btn-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
          </button>
        </form>
      </div>
    </div>
  </section>
</div>

<!-- FOOTER -->
<footer class="bg-gray-50 dark:bg-neutral-900 border-t border-gray-100 py-8 dark:border-none">
  <div class="max-w-7xl mx-auto px-5 flex flex-col sm:flex-row items-center justify-between gap-4 text-md text-gray-400">
    <span class="font-bold text-gray-900 dark:text-white">ICT<span class="text-indigo-500">Tech</span></span>
    <span>© 2025 ICTTech. All rights reserved.</span>
    <div class="flex gap-5">
      <a href="#" class="glass-icon-btn px-3 py-1.5 rounded-full border border-transparent hover:text-gray-700 dark:hover:text-white transition-colors">About</a>
      <a href="{{ route('contact') }}" class="glass-icon-btn px-3 py-1.5 rounded-full border border-transparent hover:text-gray-700 dark:hover:text-white transition-colors">Contact</a>
    </div>
  </div>
</footer>
@endsection

@section('extra-scripts')
<script>
  function handleSubmit(e) {
    e.preventDefault();
    const name = document.getElementById('field-name');
    const email = document.getElementById('field-email');
    const message = document.getElementById('field-message');
    let valid = true;

    // Clear errors
    document.querySelectorAll('.error-msg').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.form-input').forEach(el => el.classList.remove('border-red-400'));

    if (!name.value.trim()) {
      document.getElementById('err-name').classList.remove('hidden');
      name.classList.add('border-red-400');
      valid = false;
    }

    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim() || !emailRe.test(email.value)) {
      document.getElementById('err-email').classList.remove('hidden');
      email.classList.add('border-red-400');
      valid = false;
    }

    if (!message.value.trim()) {
      document.getElementById('err-message').classList.remove('hidden');
      message.classList.add('border-red-400');
      valid = false;
    }

    if (!valid) return;

    const btn = document.getElementById('submit-btn');
    const txt = document.getElementById('btn-text');
    const spinner = document.getElementById('btn-spinner');
    btn.disabled = true;
    txt.textContent = 'Sending…';
    spinner.classList.remove('hidden');

    const form = document.getElementById('contact-form');
    fetch('{{ route('contact.submit') }}', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        name: name.value.trim(),
        email: email.value.trim(),
        subject: form.subject.value,
        message: message.value.trim(),
      }),
    })
      .then(res => {
        if (!res.ok) throw new Error('Request failed');
        form.classList.add('hidden');
        document.getElementById('form-success').classList.remove('hidden');
      })
      .catch(() => {
        btn.disabled = false;
        txt.textContent = 'Send Message';
        spinner.classList.add('hidden');
        showToast('Something went wrong — please try again.');
      });
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: 0.1 });
  document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>
@endsection
