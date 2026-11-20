@extends('layouts.app')

@section('title', 'Privacy Policy — ICTTech')

@section('content')
<div class="pt-24 pb-20">
  <div class="max-w-3xl mx-auto px-5">
    <h1 class="font-display font-bold text-3xl sm:text-4xl text-gray-900 dark:text-white mb-2">Privacy Policy</h1>
    <p class="text-sm text-gray-400 mb-10">Last updated: September 30, 2026</p>

    <div class="prose prose-gray dark:prose-invert max-w-none space-y-8 text-gray-600 dark:text-gray-300 leading-relaxed">
      <p>
        ICTTech ("we," "us") operates ict-tech.ictcenter.blog. This page explains what information we
        collect from visitors and customers, and how we use it.
      </p>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">Information we collect</h2>
        <ul class="list-disc pl-5 space-y-2">
          <li><strong>Account information</strong> — when you create an account or sign in with Google, we collect your name and email address.</li>
          <li><strong>Contact form</strong> — if you submit our contact form, we collect your name, email, and message.</li>
          <li><strong>Checkout details</strong> — when placing an order, we collect a phone number and a delivery location (either typed in or shared via your device's location) so we can arrange delivery.</li>
          <li><strong>Favorites</strong> — if you save products to your favorites list, we store which products you've saved against your account.</li>
        </ul>
      </section>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">How we use this information</h2>
        <p>We use this information only to operate the site: to manage your account, respond to inquiries, and fulfill and deliver orders. We do not sell your information to third parties.</p>
      </section>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">Google Sign-In</h2>
        <p>If you choose to sign in with Google, we receive your name and email address from Google to create or access your account. We do not receive your Google password or access any other Google account data.</p>
      </section>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">Cookies and sessions</h2>
        <p>We use session cookies to keep you signed in. We don't use third-party advertising or tracking cookies.</p>
      </section>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">Third-party services</h2>
        <p>Pages on this site may load fonts, maps, or QR codes from third-party services (Google Fonts, OpenStreetMap, and a QR code generator) to display content. These services may receive standard technical information (like your IP address) as part of loading that content.</p>
      </section>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">Data retention</h2>
        <p>We keep your account information for as long as your account is active. You can ask us to delete your account and associated data at any time by contacting us.</p>
      </section>

      <section>
        <h2 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-3">Contact us</h2>
        <p>Questions about this policy? Contact us at <a href="mailto:vechakasysothonyeong@gmail.com" class="text-indigo-500 hover:underline">vechakasysothonyeong@gmail.com</a>.</p>
      </section>
    </div>

    <p class="mt-12">
      <a href="{{ route('home') }}" class="text-sm text-indigo-500 hover:underline">← Back to store</a>
    </p>
  </div>
</div>
@endsection
