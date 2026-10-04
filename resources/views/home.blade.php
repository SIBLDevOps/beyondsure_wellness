<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BeyondSure+ — Wellness &amp; Protection Plans</title>
    <meta name="description" content="BeyondSure+ wellness and protection plans — complimentary health benefits, curated wellness products, and a rewarding partner community.">
    @vite('resources/css/app.css')
</head>
<body class="bg-white text-ink-800 antialiased">

    {{-- Header --}}
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-ink-100">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-bold text-ink-900">BeyondSure<span class="text-brand-600">+</span></a>

            <input type="checkbox" id="nav-toggle" class="peer hidden">

            <nav class="hidden lg:flex items-center gap-8 text-sm font-medium text-ink-600">
                <a href="#about" class="hover:text-brand-700">About</a>
                <a href="#vision" class="hover:text-brand-700">Vision</a>
                <a href="#plans" class="hover:text-brand-700">Plans</a>
                <a href="#benefits" class="hover:text-brand-700">Benefits</a>
                <a href="#contact" class="hover:text-brand-700">Contact</a>
            </nav>

            <div class="hidden lg:flex items-center gap-3">
                <a href="{{ route('login') }}" class="text-sm font-medium text-ink-600 hover:text-brand-700 px-3 py-2">Log in</a>
                <a href="{{ route('register') }}" class="text-sm font-medium bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg transition">Become a Partner</a>
            </div>

            <label for="nav-toggle" class="lg:hidden cursor-pointer p-2 -mr-2 text-ink-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </label>

            <div class="hidden peer-checked:flex lg:hidden absolute top-16 left-0 right-0 bg-white border-b border-ink-100 flex-col p-4 gap-1 shadow-lg">
                <a href="#about" class="px-3 py-2.5 rounded-lg hover:bg-brand-50 text-ink-700 font-medium">About</a>
                <a href="#vision" class="px-3 py-2.5 rounded-lg hover:bg-brand-50 text-ink-700 font-medium">Vision</a>
                <a href="#plans" class="px-3 py-2.5 rounded-lg hover:bg-brand-50 text-ink-700 font-medium">Plans</a>
                <a href="#benefits" class="px-3 py-2.5 rounded-lg hover:bg-brand-50 text-ink-700 font-medium">Benefits</a>
                <a href="#contact" class="px-3 py-2.5 rounded-lg hover:bg-brand-50 text-ink-700 font-medium">Contact</a>
                <div class="border-t border-ink-100 my-2"></div>
                <a href="{{ route('login') }}" class="px-3 py-2.5 rounded-lg hover:bg-brand-50 text-ink-700 font-medium">Log in</a>
                <a href="{{ route('register') }}" class="px-3 py-2.5 rounded-lg bg-brand-600 text-white font-medium text-center">Become a Partner</a>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="bg-hero-gradient text-white">
        <div class="max-w-6xl mx-auto px-6 py-20 sm:py-28 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-block bg-white/10 border border-white/20 text-brand-200 text-xs font-semibold tracking-wide uppercase px-3 py-1 rounded-full">
                    {{ $content['site_hero_badge'] }}
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-bold leading-tight">
                    {{ $content['site_hero_title'] }}
                </h1>
                <p class="mt-5 text-lg text-ink-100/90 max-w-lg">
                    {{ $content['site_hero_subtitle'] }}
                </p>
                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="#plans" class="bg-brand-500 hover:bg-brand-400 text-ink-900 font-semibold px-6 py-3 rounded-lg transition">
                        Explore plans
                    </a>
                    <a href="{{ route('register') }}" class="border border-white/30 hover:bg-white/10 font-semibold px-6 py-3 rounded-lg transition">
                        Get started free
                    </a>
                </div>
                <div class="mt-10 grid grid-cols-3 gap-6 max-w-md">
                    <div>
                        <div class="text-2xl font-bold">{{ $planCount }}</div>
                        <div class="text-xs text-ink-100/70 mt-1">Wellness bundles</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ $benefitGroups->count() }}+</div>
                        <div class="text-xs text-ink-100/70 mt-1">Benefit categories</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ $memberCount }}</div>
                        <div class="text-xs text-ink-100/70 mt-1">Members onboard</div>
                    </div>
                </div>
            </div>

            <div class="relative hidden lg:block">
                <div class="absolute -inset-6 bg-brand-500/20 rounded-3xl blur-2xl"></div>
                <div class="relative bg-white/5 border border-white/10 rounded-3xl p-6 backdrop-blur">
                    <svg viewBox="0 0 400 340" class="w-full h-auto">
                        <rect x="20" y="30" width="360" height="280" rx="20" fill="#0f2b26" stroke="#10b981" stroke-opacity="0.4"/>
                        <circle cx="200" cy="140" r="70" fill="#059669" fill-opacity="0.35"/>
                        <path d="M170 140 l20 20 l40 -45" stroke="#6ee7b7" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="60" y="240" width="90" height="14" rx="7" fill="#34d399" fill-opacity="0.6"/>
                        <rect x="60" y="264" width="140" height="10" rx="5" fill="#ffffff" fill-opacity="0.2"/>
                        <rect x="250" y="240" width="90" height="14" rx="7" fill="#fbbf24" fill-opacity="0.7"/>
                        <rect x="250" y="264" width="90" height="10" rx="5" fill="#ffffff" fill-opacity="0.2"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    {{-- About --}}
    <section id="about" class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-2 gap-12 items-center">
        <div>
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide">About BeyondSure+</span>
            <h2 class="mt-2 text-3xl font-bold text-ink-900">Wellness made simple, accessible and rewarding</h2>
            <p class="mt-4 text-ink-600 leading-relaxed">{{ $content['site_about_text_1'] }}</p>
            <p class="mt-4 text-ink-600 leading-relaxed">{{ $content['site_about_text_2'] }}</p>
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div class="text-sm text-ink-600"><strong class="text-ink-900 block">Transparent pricing</strong>Every rupee accounted for</div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div class="text-sm text-ink-600"><strong class="text-ink-900 block">Real products</strong>Wellness you'll actually use</div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div class="text-sm text-ink-600"><strong class="text-ink-900 block">Community-driven</strong>Grow with your network</div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div class="text-sm text-ink-600"><strong class="text-ink-900 block">Always supported</strong>Real people, real help</div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-brand-50 rounded-2xl p-6 aspect-square flex flex-col justify-end">
                <svg class="w-8 h-8 text-brand-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21c-4.97-4-8-7.58-8-11a8 8 0 1116 0c0 3.42-3.03 7-8 11z"/><circle cx="12" cy="10" r="3" stroke-width="1.5"/></svg>
                <div class="font-semibold text-ink-900 text-sm">Pan-India delivery</div>
            </div>
            <div class="bg-ink-900 text-white rounded-2xl p-6 aspect-square flex flex-col justify-end mt-8">
                <svg class="w-8 h-8 text-brand-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="font-semibold text-sm">Verified wellness partners</div>
            </div>
            <div class="bg-gold-400/20 rounded-2xl p-6 aspect-square flex flex-col justify-end -mt-8">
                <svg class="w-8 h-8 text-gold-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <div class="font-semibold text-ink-900 text-sm">Fast payout dispatch</div>
            </div>
            <div class="bg-brand-600 text-white rounded-2xl p-6 aspect-square flex flex-col justify-end">
                <svg class="w-8 h-8 text-white mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a4 4 0 10-8 0"/></svg>
                <div class="font-semibold text-sm">Growing partner network</div>
            </div>
        </div>
    </section>

    {{-- Vision --}}
    <section id="vision" class="bg-ink-50">
        <div class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-3 gap-10">
            <div>
                <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide">Our vision</span>
                <h2 class="mt-2 text-3xl font-bold text-ink-900">A wellness-first future for every household</h2>
                <p class="mt-4 text-ink-600 leading-relaxed">{{ $content['site_vision_text'] }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-ink-100">
                <div class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center font-bold mb-4">01</div>
                <h3 class="font-semibold text-ink-900">Our mission</h3>
                <p class="mt-2 text-sm text-ink-600 leading-relaxed">{{ $content['site_mission_text'] }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-ink-100">
                <div class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center font-bold mb-4">02</div>
                <h3 class="font-semibold text-ink-900">Our promise</h3>
                <p class="mt-2 text-sm text-ink-600 leading-relaxed">{{ $content['site_promise_text'] }}</p>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="max-w-6xl mx-auto px-6 py-20">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide">How it works</span>
            <h2 class="mt-2 text-3xl font-bold text-ink-900">Get started in three simple steps</h2>
        </div>
        <div class="mt-12 grid sm:grid-cols-3 gap-8">
            @foreach ([
                ['n' => '1', 'title' => 'Create your account', 'text' => 'Sign up in minutes with just your mobile number — no paperwork.'],
                ['n' => '2', 'title' => 'Choose your plan', 'text' => 'Pick the wellness bundle that fits your household, from Essential to Elite.'],
                ['n' => '3', 'title' => 'Enjoy the benefits', 'text' => 'Get your products, unlock complimentary services, and start earning as a partner.'],
            ] as $step)
                <div class="text-center">
                    <div class="w-14 h-14 rounded-full bg-brand-600 text-white font-bold text-xl flex items-center justify-center mx-auto">{{ $step['n'] }}</div>
                    <h3 class="mt-4 font-semibold text-ink-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm text-ink-600">{{ $step['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Plans --}}
    <section id="plans" class="bg-ink-50">
        <div class="max-w-6xl mx-auto px-6 py-20">
            <div class="text-center max-w-2xl mx-auto">
                <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide">Plans</span>
                <h2 class="mt-2 text-3xl font-bold text-ink-900">A bundle for every household</h2>
                <p class="mt-3 text-ink-600">Transparent pricing, real wellness products, complimentary benefits included.</p>
            </div>

            <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @forelse ($plans as $i => $plan)
                    <div class="bg-white rounded-2xl border {{ $i === 2 ? 'border-brand-500 ring-2 ring-brand-500' : 'border-ink-100' }} p-6 flex flex-col relative">
                        @if ($i === 2)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-600 text-white text-xs font-semibold px-3 py-1 rounded-full">Most popular</span>
                        @endif
                        <div class="font-bold text-lg text-ink-900">{{ $plan->name }}</div>
                        <p class="text-xs text-ink-500 mt-1 min-h-[32px]">{{ $plan->description }}</p>
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-ink-900">₹{{ number_format($plan->price, 0) }}</span>
                        </div>
                        <ul class="mt-4 space-y-2 text-sm text-ink-600 flex-1">
                            @foreach ($plan->products as $product)
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $product->pivot->qty }}× {{ $product->name }}
                                </li>
                            @endforeach
                            <li class="flex items-center gap-2 text-brand-700 font-medium">
                                <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Complimentary benefits included
                            </li>
                        </ul>
                        <a href="{{ route('buy.start', $plan) }}" class="mt-6 block text-center rounded-lg py-2.5 font-semibold text-sm transition
                            {{ $i === 2 ? 'bg-brand-600 hover:bg-brand-700 text-white' : 'bg-brand-50 hover:bg-brand-100 text-brand-700' }}">
                            Choose {{ $plan->name }}
                        </a>
                    </div>
                @empty
                    <p class="text-ink-500 col-span-4 text-center">Plans are being set up — check back soon.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Benefits --}}
    <section id="benefits" class="max-w-6xl mx-auto px-6 py-20">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide">Complimentary benefits</span>
            <h2 class="mt-2 text-3xl font-bold text-ink-900">Every plan unlocks more than products</h2>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @forelse ($benefitGroups as $group)
                <div class="rounded-2xl border border-ink-100 p-6 hover:border-brand-300 hover:shadow-sm transition">
                    <div class="w-11 h-11 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v8m-4-4h8m5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-ink-900">{{ $group }}</h3>
                    <p class="mt-1 text-sm text-ink-500">Included free with eligible plan bundles.</p>
                </div>
            @empty
                <p class="text-ink-500 col-span-4 text-center">Benefit categories are being finalized.</p>
            @endforelse
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="bg-ink-900 text-white">
        <div class="max-w-6xl mx-auto px-6 py-20">
            <div class="text-center max-w-2xl mx-auto">
                <span class="text-brand-400 font-semibold text-sm uppercase tracking-wide">What members say</span>
                <h2 class="mt-2 text-3xl font-bold">Real households, real wellness</h2>
            </div>
            <div class="mt-12 grid sm:grid-cols-3 gap-6">
                @forelse ($testimonials as $t)
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
                        <div class="flex gap-1 text-gold-400 mb-3">
                            @for ($i = 0; $i < $t->rating; $i++)
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M10 1l2.6 5.9L19 7.6l-4.5 4.2 1.2 6.2L10 15l-5.7 3 1.2-6.2L1 7.6l6.4-.7z"/></svg>
                            @endfor
                        </div>
                        <p class="text-sm text-ink-100/90 leading-relaxed">&ldquo;{{ $t->message }}&rdquo;</p>
                        <div class="mt-4 text-sm font-semibold">{{ $t->name }} @if($t->role)<span class="text-ink-100/50 font-normal">— {{ $t->role }}</span>@endif</div>
                    </div>
                @empty
                    <p class="text-ink-100/60 col-span-3 text-center">Member stories are on the way.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- CTA banner --}}
    <section class="bg-brand-600">
        <div class="max-w-6xl mx-auto px-6 py-14 flex flex-col sm:flex-row items-center justify-between gap-6 text-white">
            <div>
                <h2 class="text-2xl font-bold">Ready to start your wellness journey?</h2>
                <p class="text-brand-100 mt-1">Join in minutes — no paperwork, no hidden fees.</p>
            </div>
            <a href="{{ route('register') }}" class="bg-white text-brand-700 font-semibold px-6 py-3 rounded-lg hover:bg-brand-50 transition shrink-0">
                Create free account
            </a>
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="max-w-6xl mx-auto px-6 py-20">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide">Contact us</span>
            <h2 class="mt-2 text-3xl font-bold text-ink-900">We'd love to hear from you</h2>
            <p class="mt-3 text-ink-600">Questions about a plan, a payout, or becoming a partner — reach out any time.</p>
        </div>

        <div class="mt-12 grid lg:grid-cols-5 gap-10">
            <div class="lg:col-span-2 space-y-6">
                <div class="flex items-start gap-4">
                    <span class="w-10 h-10 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3" stroke-width="1.5"/></svg>
                    </span>
                    <div>
                        <div class="font-semibold text-ink-900 text-sm">Office</div>
                        <div class="text-sm text-ink-600">{{ $content['site_contact_address'] }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <span class="w-10 h-10 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h2.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11 11 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </span>
                    <div>
                        <div class="font-semibold text-ink-900 text-sm">Phone</div>
                        <div class="text-sm text-ink-600">{{ $content['site_contact_phone'] }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <span class="w-10 h-10 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </span>
                    <div>
                        <div class="font-semibold text-ink-900 text-sm">Email</div>
                        <div class="text-sm text-ink-600">{{ $content['site_contact_email'] }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <span class="w-10 h-10 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <div class="font-semibold text-ink-900 text-sm">Hours</div>
                        <div class="text-sm text-ink-600">{{ $content['site_contact_hours'] }}</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-brand-50 border border-brand-200 text-brand-700 text-sm px-4 py-3">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}#contact" class="bg-ink-50 rounded-2xl p-6 space-y-4">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-700">Name <span class="text-rose-500">*</span></label>
                            <input name="name" value="{{ old('name') }}" required
                                class="mt-1 w-full rounded-lg border @error('name') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-ink-100 focus:border-brand-500 focus:ring-brand-500 @enderror bg-white text-sm px-3 py-2 transition">
                            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700">Email <span class="text-rose-500">*</span></label>
                            <input name="email" type="email" value="{{ old('email') }}" required
                                class="mt-1 w-full rounded-lg border @error('email') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-ink-100 focus:border-brand-500 focus:ring-brand-500 @enderror bg-white text-sm px-3 py-2 transition">
                            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-700">Phone <span class="text-ink-400">(optional)</span></label>
                            <input name="phone" type="tel" value="{{ old('phone') }}" placeholder="10-digit mobile"
                                pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric"
                                class="mt-1 w-full rounded-lg border @error('phone') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-ink-100 focus:border-brand-500 focus:ring-brand-500 @enderror bg-white text-sm px-3 py-2 transition">
                            @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700">Subject <span class="text-ink-400">(optional)</span></label>
                            <input name="subject" value="{{ old('subject') }}" placeholder="What is this about?"
                                class="mt-1 w-full rounded-lg border @error('subject') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-ink-100 focus:border-brand-500 focus:ring-brand-500 @enderror bg-white text-sm px-3 py-2 transition">
                            @error('subject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700">Message <span class="text-rose-500">*</span></label>
                        <textarea name="message" rows="4" required
                            class="mt-1 w-full rounded-lg border @error('message') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-ink-100 focus:border-brand-500 focus:ring-brand-500 @enderror bg-white text-sm px-3 py-2 transition">{{ old('message') }}</textarea>
                        @error('message') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="w-full sm:w-auto bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-lg transition">
                        Send message
                    </button>
                </form>
            </div>
        </div>
    </section>

    @include('partials.public-footer')
</body>
</html>
