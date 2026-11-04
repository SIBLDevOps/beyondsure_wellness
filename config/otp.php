<?php

return [
    /*
     * Show the one-time code on the sign-in / register screen (and write it to the log).
     *
     * There is no SMS gateway connected yet, so a UAT or local server needs this to be able to sign in. It is OFF by
     * default. NEVER turn it on for a live site: anyone could then sign in as any member just by typing their number.
     * Servers whose APP_ENV is local, testing, uat or staging show the code even without this switch. A UAT server that
     * runs with APP_ENV=production needs this switch on. A live site: APP_ENV=production and this switch off.
     */
    'show_on_screen' => (bool) env('OTP_SHOW_ON_SCREEN', false),
];
