<?php

return [
    /*
     * Show the one-time code on the sign-in / register screen (and write it to the log).
     *
     * There is no SMS gateway connected yet, so a UAT or local server needs this to be able to sign in. It is OFF by
     * default. NEVER turn it on for a live site: anyone could then sign in as any member just by typing their number.
     * Local machines with APP_DEBUG=true show the code even without this switch.
     */
    'show_on_screen' => (bool) env('OTP_SHOW_ON_SCREEN', false),
];
