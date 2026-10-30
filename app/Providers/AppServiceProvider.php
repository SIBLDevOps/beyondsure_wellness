<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lets views write Site::name() instead of the full class name.
        \Illuminate\Foundation\AliasLoader::getInstance()->alias('Site', \App\Support\Site::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brute-force and flooding limits. Keyed so one noisy visitor cannot lock everyone else out.
        RateLimiter::for('admin-login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('otp-send', fn (Request $r) => Limit::perMinute(12)->by($r->ip()));
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        // The authenticator code step: five tries a minute for that half-finished sign-in, so six digits cannot be guessed.
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by('2fa|'.($r->session()->get('two_factor.user_id') ?? 'none').'|'.$r->ip()));
        RateLimiter::for('contact', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        // Emails a member can trigger: per member, so one person cannot use us to spam, and others are not affected.
        RateLimiter::for('invoice-email', fn (Request $r) => Limit::perMinute(10)->by('inv|'.($r->user('member')?->id ?? $r->ip())));
        RateLimiter::for('member-invite', fn (Request $r) => Limit::perHour(12)->by('inv|'.($r->user('member')?->id ?? $r->ip())));
    }
}
