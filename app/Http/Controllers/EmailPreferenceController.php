<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Response;

/** The unsubscribe link in optional emails. The link is signed, so it only works for the member it was made for. */
class EmailPreferenceController extends Controller
{
    public function unsubscribe(Member $member): Response
    {
        $member->forceFill(['email_opt_out' => true])->save();

        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Unsubscribed</title></head>'
            .'<body style="font-family:system-ui,Arial,sans-serif;background:#f3f4f6;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
            .'<div style="background:#fff;border-radius:12px;padding:32px;max-width:420px;text-align:center;box-shadow:0 2px 18px rgba(0,0,0,.08)">'
            .'<h1 style="font-size:20px;margin:0 0 8px">You are unsubscribed</h1>'
            .'<p style="color:#6b7280;margin:0">You will no longer get news and offers by email. Emails about your own orders, payments and money will still be sent.</p>'
            .'</div></body></html>'
        );
    }
}
