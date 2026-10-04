<footer class="bg-ink-900 text-ink-100">
    <div class="max-w-6xl mx-auto px-6 py-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="text-xl font-bold text-white">BeyondSure<span class="text-brand-400">+</span></div>
            <p class="mt-3 text-sm text-ink-400 leading-relaxed">
                Wellness and protection plans built around everyday health — complimentary services, curated
                wellness products, and a rewarding partner community.
            </p>
            <div class="flex items-center gap-3 mt-5">
                @foreach ([
                    'M22 12a10 10 0 10-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0022 12z',
                    'M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.4.5.6.2 1 .5 1.5 1 .4.4.7.8 1 1.5.2.5.4 1.2.5 2.4.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.4-.3.6-.6 1-1 1.5-.4.4-.8.7-1.5 1-.5.2-1.2.4-2.4.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.4-.5-.6-.3-1-.6-1.5-1-.4-.4-.7-.8-1-1.5-.2-.5-.4-1.2-.5-2.4C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.4.2-.6.5-1 1-1.5.4-.4.8-.7 1.5-1 .5-.2 1.2-.4 2.4-.5C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.7.1-1 .1-1.6.2-1.9.4-.5.2-.8.4-1.2.7-.3.3-.6.7-.7 1.2-.2.3-.3.9-.4 1.9-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1 1 .2 1.6.4 1.9.2.5.4.8.7 1.2.3.3.7.6 1.2.7.3.2.9.3 1.9.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1-.1 1.6-.2 1.9-.4.5-.2.8-.4 1.2-.7.3-.3.6-.7.7-1.2.2-.3.3-.9.4-1.9.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-1-.2-1.6-.4-1.9-.2-.5-.4-.8-.7-1.2-.3-.3-.7-.6-1.2-.7-.3-.2-.9-.3-1.9-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.5a4.5 4.5 0 110 9 4.5 4.5 0 010-9zm0 1.8a2.7 2.7 0 100 5.4 2.7 2.7 0 000-5.4zm5.7-2a1.1 1.1 0 110 2.1 1.1 1.1 0 010-2.1z',
                    'M20 5.3a8 8 0 01-2.3.6 4 4 0 001.8-2.2 8 8 0 01-2.5 1 4 4 0 00-6.8 3.6A11.3 11.3 0 014 4.9a4 4 0 001.2 5.3 4 4 0 01-1.8-.5v.1a4 4 0 003.2 3.9 4 4 0 01-1.8.1 4 4 0 003.7 2.8A8 8 0 013 18.4a11.3 11.3 0 006.1 1.8c7.3 0 11.3-6 11.3-11.3v-.5A8 8 0 0022 6.1 8 8 0 0020 5.3z',
                ] as $icon)
                    <span class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 fill-ink-100"><path d="{{ $icon }}"/></svg>
                    </span>
                @endforeach
            </div>
        </div>

        <div>
            <div class="text-sm font-semibold text-white uppercase tracking-wide">Company</div>
            <ul class="mt-3 space-y-2 text-sm text-ink-400">
                <li><a href="{{ route('home') }}#about" class="hover:text-brand-300">About us</a></li>
                <li><a href="{{ route('home') }}#vision" class="hover:text-brand-300">Our vision</a></li>
                <li><a href="{{ route('home') }}#plans" class="hover:text-brand-300">Plans</a></li>
                <li><a href="{{ route('home') }}#benefits" class="hover:text-brand-300">Benefits</a></li>
            </ul>
        </div>

        <div>
            <div class="text-sm font-semibold text-white uppercase tracking-wide">Account</div>
            <ul class="mt-3 space-y-2 text-sm text-ink-400">
                <li><a href="{{ route('login') }}" class="hover:text-brand-300">Member login</a></li>
                <li><a href="{{ route('register') }}" class="hover:text-brand-300">Become a partner</a></li>
                <li><a href="{{ route('admin.login') }}" class="hover:text-brand-300">Admin login</a></li>
            </ul>
        </div>

        <div>
            <div class="text-sm font-semibold text-white uppercase tracking-wide">Contact</div>
            <ul class="mt-3 space-y-2 text-sm text-ink-400">
                <li>{{ $content['site_contact_address'] ?? 'Wellness Tower, MG Road, Bengaluru, Karnataka 560001' }}</li>
                <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $content['site_contact_phone'] ?? '+911140001234') }}" class="hover:text-brand-300">{{ $content['site_contact_phone'] ?? '+91 11 4000 1234' }}</a></li>
                <li><a href="mailto:{{ $content['site_contact_email'] ?? 'support@beyondsure.example' }}" class="hover:text-brand-300">{{ $content['site_contact_email'] ?? 'support@beyondsure.example' }}</a></li>
                <li>{{ $content['site_contact_hours'] ?? 'Mon–Sat, 9:00 AM – 7:00 PM IST' }}</li>
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-6xl mx-auto px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-ink-400">
            <div>&copy; {{ date('Y') }} BeyondSure Wellness Pvt. Ltd. All rights reserved.</div>
            <div class="flex gap-4">
                <span>Terms of Service</span>
                <span>Privacy Policy</span>
                <span>Refund Policy</span>
            </div>
        </div>
    </div>
</footer>
