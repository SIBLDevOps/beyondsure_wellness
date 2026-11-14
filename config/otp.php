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

    /*
     * Send the one-time code to the member's mobile by SMS through the True Alpha SMS API:
     *   POST {url}   {"template_slug": "bima_new_otp", "mobile": "9876543210", "values": ["XYZ", "876767", "BeyondSure+"]}
     * The values are, in order: the person's name, the code, the brand. It is OFF until OTP_SMS_ENABLED=true. The API key issued for the
     * SMS API (OTP_SMS_TOKEN) is REQUIRED: it is sent as "Authorization: Bearer ...", and without it the API answers
     * 401 SMS_API_KEY_REQUIRED. Nothing is logged except the masked number and the API's reason.
     */
    /*
     * Admin sign-in with a one-time code texted to the staff member's mobile, after the email and password.
     * OFF until ADMIN_OTP_ENABLED=true. Staff who have not saved a mobile number yet still sign in with the password
     * and are asked to add one. Uses the SMS settings below.
     */
    'admin' => [
        'enabled' => (bool) env('ADMIN_OTP_ENABLED', false),
    ],

    'sms' => [
        'enabled' => (bool) env('OTP_SMS_ENABLED', false),
        'url' => env('OTP_SMS_URL', 'https://preprod.truealpha.in/api/sms/v1/send'),
        'template_slug' => env('OTP_SMS_TEMPLATE_SLUG', 'bima_new_otp'),
        'brand' => env('OTP_SMS_BRAND'),
        'token' => env('OTP_SMS_TOKEN'),
        'timeout' => (int) env('OTP_SMS_TIMEOUT', 8),
    ],
];
