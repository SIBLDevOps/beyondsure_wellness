<?php

namespace App\Support;

use App\Models\Member;

class Reminder
{
    /**
     * A ready-to-send WhatsApp message for someone who registered but has not paid yet. It opens WhatsApp with the text
     * filled in, so the sponsor only has to press send. Indian mobile numbers (10 digits) get the 91 country code.
     */
    public static function whatsappUrl(Member $person, ?string $planName = null, ?string $brand = null): string
    {
        $digits = preg_replace('/\D+/', '', (string) $person->phone);
        $number = strlen($digits) === 10 ? '91'.$digits : $digits;

        // Lists build many links: let them look the brand name up once and pass it in.
        $text = 'Hi '.$person->name.', you registered on '.($brand ?? Site::name())
            .($planName ? ' for the '.$planName.' plan' : '')
            .' but the payment is not done yet. You can finish it here: '.route('login');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }
}
