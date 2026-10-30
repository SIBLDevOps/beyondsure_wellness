<?php

namespace App\Services;

use App\Mail\BrandedMail;
use App\Models\Announcement;
use App\Models\ContactMessage;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Withdrawal;
use App\Support\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Every email the business sends. Each method writes the message and hands it over; none of them ever throws, so a mail
 * server that is down, or a member with no address, can never stop a payment, a withdrawal or a cycle from going through.
 * Emails about a member's own orders, payments and money are always sent. Announcements and new-plan news are optional
 * and carry an unsubscribe link.
 */
class MemberMailer
{
    private function money(float|string|null $v): string
    {
        return '₹'.number_format((float) $v, 2);
    }

    /** The company's own address for alerts. Ignored while it is still the placeholder text from the website settings. */
    public function officeEmail(): ?string
    {
        $email = trim((string) Setting::get('site_contact_email', ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/(\.example|@example\.(com|org|net))$/i', $email)) {
            return null;
        }

        return $email;
    }

    private function deliver(?string $to, BrandedMail $mail): void
    {
        $to = trim((string) $to);

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Email "'.$mail->mailSubject.'" to '.$to.' was not sent: '.$e->getMessage());
        }
    }

    private function toMember(Member $member, BrandedMail $mail, bool $optional = false): void
    {
        if ($optional) {
            if ($member->email_opt_out) {
                return;
            }
            $mail->unsubscribeUrl = URL::signedRoute('email.unsubscribe', ['member' => $member->id]);
        }

        $this->deliver($member->email, $mail);
    }

    private function toOffice(BrandedMail $mail): void
    {
        $this->deliver($this->officeEmail(), $mail);
    }

    public static function invoiceUrl(Order $order): string
    {
        return URL::temporarySignedRoute('invoice.shared', now()->addDays(30), ['order' => $order->id]);
    }

    // ------------------------------------------------------------------------------------------ joining

    public function welcome(Member $member): void
    {
        $plan = $member->interestedPlan?->name;

        $this->toMember($member, new BrandedMail(
            'Welcome to '.Site::name(),
            'Welcome, '.$member->name,
            array_filter([
                'Your account is ready.',
                $plan ? "To activate it, pay for the {$plan} plan you chose. You join your sponsor's team as soon as your payment is approved." : 'Choose a plan to activate your account. You join your sponsor\'s team as soon as your payment is approved.',
            ]),
            ['Your member ID' => $member->member_code],
            'Open my account',
            route('member.plans.index'),
        ));
    }

    public function sponsorNewMember(Member $new): void
    {
        $sponsor = $new->sponsor;
        if (! $sponsor) {
            return;
        }

        $this->toMember($sponsor, new BrandedMail(
            $new->name.' joined your team',
            $new->name.' joined using your link',
            ['They will show in your team and tree once their payment is approved. Until then you can follow them in Pending customers.'],
            array_filter(['Name' => $new->name, 'Plan chosen' => $new->interestedPlan?->name]),
            'See pending customers',
            route('member.pending.index'),
            'Hi '.$sponsor->name.',',
        ));
    }

    // ------------------------------------------------------------------------------------------ buying

    public function orderPlaced(Order $order): void
    {
        $order->loadMissing('member', 'plan');

        $this->toMember($order->member, new BrandedMail(
            'Complete your payment for '.$order->plan->name,
            'One step left: complete your payment',
            ['Your order is placed. Pay it to activate your account.'],
            ['Order' => $order->order_code, 'Plan' => $order->plan->name, 'Amount to pay (incl. GST)' => $this->money($order->amount)],
            'Pay now',
            route('member.orders.pay', $order),
            'Hi '.$order->member->name.',',
        ));
    }

    public function paymentReceived(Order $order): void
    {
        $order->loadMissing('member', 'plan', 'payment');

        $this->toMember($order->member, new BrandedMail(
            'We have your payment',
            'We have your payment',
            ['Our team is checking it now. Once it is confirmed your account becomes active and your wallet, team and earnings open up.'],
            array_filter(['Order' => $order->order_code, 'Amount' => $this->money($order->amount), 'Method' => str_replace('_', ' ', (string) $order->payment?->method), 'Reference' => $order->payment?->reference]),
            'View my orders',
            route('member.orders.index'),
            'Hi '.$order->member->name.',',
        ));

        $this->toOffice(new BrandedMail(
            'Payment to verify: '.$order->order_code.' ('.$this->money($order->amount).')',
            'A payment is waiting for you',
            [$order->member->name.' has sent a payment. Check your bank, then verify it.'],
            ['Member' => $order->member->name.' ('.$order->member->member_code.')', 'Order' => $order->order_code, 'Amount' => $this->money($order->amount), 'Reference' => (string) $order->payment?->reference],
            'Verify payments',
            route('admin.payments.index'),
        ));
    }

    public function purchaseConfirmed(Order $order): void
    {
        $order->loadMissing('member', 'plan');

        $this->toMember($order->member, new BrandedMail(
            'Payment confirmed'.($order->invoice_number ? ' — invoice '.$order->invoice_number : ''),
            'Payment confirmed. Thank you!',
            ['Your '.$order->plan->name.' plan is active. Your invoice is ready: you can open it, print it or save it as a PDF.'],
            array_filter(['Invoice no.' => $order->invoice_number, 'Order' => $order->order_code, 'Plan' => $order->plan->name, 'Total paid' => $this->money($order->amount)]),
            'View invoice',
            self::invoiceUrl($order),
            'Hi '.$order->member->name.',',
        ));
    }

    public function sponsorMemberActive(Member $new): void
    {
        $sponsor = $new->sponsor;
        if (! $sponsor) {
            return;
        }

        $this->toMember($sponsor, new BrandedMail(
            $new->name.' is now active',
            $new->name.' is now part of your team',
            ['Their payment was approved, so they now count in your team, tree and levels.'],
            ['Member' => $new->name, 'Member ID' => $new->member_code],
            'See my team',
            route('member.team.index'),
            'Hi '.$sponsor->name.',',
        ));
    }

    public function paymentRejected(Order $order): void
    {
        $order->loadMissing('member');

        $this->toMember($order->member, new BrandedMail(
            'Your payment could not be verified',
            'We could not verify your payment',
            ['Please check the reference number against your bank receipt and send it again. If you need help, just reply to this email.'],
            ['Order' => $order->order_code, 'Amount' => $this->money($order->amount)],
            'Send payment again',
            route('member.orders.pay', $order),
            'Hi '.$order->member->name.',',
        ));
    }

    public function creditNoteIssued(\App\Models\CreditNote $note): void
    {
        $order = $note->order()->with('member', 'plan')->first();
        if (! $order) {
            return;
        }

        $this->toMember($order->member, new BrandedMail(
            'Credit note '.$note->number.' for your order',
            'Your order has been cancelled',
            ['We have cancelled your order and issued a credit note. '.$note->reason],
            array_filter(['Credit note' => $note->number, 'Original invoice' => $order->invoice_number, 'Order' => $order->order_code, 'Amount refunded' => $this->money($note->amount),
                'Refunded by' => $note->refund_method ? $note->methodLabel() : null, 'Refund reference' => $note->refund_reference]),
            'View credit note',
            route('member.orders.credit-note', $order),
            'Hi '.$order->member->name.',',
        ));
    }

    public function invoiceTo(Order $order, string $email, ?string $fromName = null): void
    {
        $order->loadMissing('plan');

        $this->deliver($email, new BrandedMail(
            'Invoice '.$order->invoice_number.' from '.Site::name(),
            'Your invoice',
            [($fromName ? $fromName.' shared' : 'Here is').' invoice '.$order->invoice_number.' for the '.$order->plan->name.' plan. The link works for 30 days.'],
            ['Invoice no.' => (string) $order->invoice_number, 'Total' => $this->money($order->amount)],
            'Open invoice',
            self::invoiceUrl($order),
        ));
    }

    // ------------------------------------------------------------------------------------------ money

    public function withdrawalRequested(Withdrawal $w): void
    {
        $w->loadMissing('member');

        $this->toMember($w->member, new BrandedMail(
            'Withdrawal request received',
            'We received your withdrawal request',
            ['You will get another email when it is approved or paid.'],
            ['Amount' => $this->money($w->amount)],
            'See my wallet',
            route('member.wallet.index'),
            'Hi '.$w->member->name.',',
        ));

        $this->toOffice(new BrandedMail(
            'Withdrawal request from '.$w->member->name.' ('.$this->money($w->amount).')',
            'A withdrawal is waiting for approval',
            [],
            ['Member' => $w->member->name.' ('.$w->member->member_code.')', 'Amount' => $this->money($w->amount)],
            'Open withdrawals',
            route('admin.withdrawals.index'),
        ));
    }

    public function withdrawalDecision(Withdrawal $w): void
    {
        $w->loadMissing('member');
        $facts = array_filter(['Amount' => $this->money($w->amount), 'Payout reference' => $w->payout_reference]);
        [$subject, $heading, $text] = match ($w->status) {
            'approved' => ['Withdrawal approved', 'Your withdrawal is approved', 'It will be paid out soon. We will email you again with the payout reference.'],
            'rejected' => ['Withdrawal declined', 'Your withdrawal was declined', 'The amount stays in your wallet. Reply to this email if you want to know why.'],
            'paid' => ['Withdrawal paid', 'Your withdrawal has been paid', 'The money was sent to your bank account. It can take a short while to show.'],
            default => [null, null, null],
        };

        if ($subject) {
            $this->toMember($w->member, new BrandedMail($subject, $heading, [$text], $facts, 'See my wallet', route('member.wallet.index'), 'Hi '.$w->member->name.',')) ;
        }
    }

    /** One email per person who earned in this cycle, with what they earned by type. */
    public function incomeCredited(Cycle $cycle): void
    {
        $labels = ['self' => 'Your own purchase', 'sponsor' => 'Sponsor bonus', 'matching' => 'Matching income', 'rank' => 'Rank pool'];

        $entries = LedgerEntry::withoutGlobalScopes()->where('cycle_id', $cycle->id)->where('amount', '>', 0)->whereIn('type', array_keys($labels))->get()->groupBy('member_id');

        foreach (Member::withoutGlobalScopes()->whereIn('id', $entries->keys())->get() as $member) {
            $rows = $entries[$member->id]->groupBy('type');
            $total = $entries[$member->id]->sum('amount');
            $facts = [];
            foreach ($labels as $type => $label) {
                if ($rows->has($type)) {
                    $facts[$label] = $this->money($rows[$type]->sum('amount'));
                }
            }
            $facts['Total'] = $this->money($total);

            $this->toMember($member, new BrandedMail(
                'You earned '.$this->money($total).' income this cycle',
                'Income added to your wallet',
                ['A new cycle was approved and your income has been added to your wallet.'],
                $facts,
                'See my wallet',
                route('member.wallet.index'),
                'Hi '.$member->name.',',
            ));
        }
    }

    public function rankReached(Member $member, string $rank): void
    {
        $this->toMember($member, new BrandedMail(
            'You reached '.ucfirst($rank).' rank',
            'Congratulations, you reached '.ucfirst($rank).' rank',
            ['Your team volume and active directs earned you a new rank. Keep growing!'],
            ['New rank' => ucfirst($rank)],
            'Open my dashboard',
            route('member.dashboard'),
            'Hi '.$member->name.',',
        ));
    }

    // ------------------------------------------------------------------------------------------ inviting and news

    public function invite(Member $sponsor, string $email, string $url, ?Plan $plan, ?string $name = null): void
    {
        $this->deliver($email, new BrandedMail(
            $sponsor->name.' invited you to join '.Site::name(),
            $sponsor->name.' invited you',
            array_filter([
                $plan ? "You are invited to join with the {$plan->name} plan already chosen for you." : 'You are invited to join and choose your plan.',
                'Open the link, verify your mobile number and you can go straight to payment.',
            ]),
            array_filter(['Invited by' => $sponsor->name, 'Plan' => $plan?->name, 'Price (incl. GST)' => $plan ? $this->money($plan->totalPayable()) : null]),
            'Accept the invite',
            $url,
            $name ? 'Hi '.$name.',' : null,
        ));
    }

    public function announcement(Announcement $a, Member $member): void
    {
        $this->toMember($member, new BrandedMail(
            $a->title,
            $a->title,
            [],
            [],
            'Open my account',
            route('member.dashboard'),
            'Hi '.$member->name.',',
            null,
            $a->body,
        ), optional: true);
    }

    public function newPlan(Plan $plan, Member $member): void
    {
        $plan->loadMissing('products');

        $this->toMember($member, new BrandedMail(
            'New plan: '.$plan->name,
            'A new plan is here: '.$plan->name,
            [],
            array_filter([
                'Included' => $plan->products->map(fn ($p) => $p->pivot->qty.' × '.$p->name)->implode(', '),
                'Price (incl. GST)' => $this->money($plan->totalPayable()),
            ]),
            'See the plan',
            route('member.plans.index'),
            'Hi '.$member->name.',',
            null,
            $plan->description,
        ), optional: true);
    }

    public function contactMessage(ContactMessage $message): void
    {
        $this->toOffice(new BrandedMail(
            'New message from '.$message->name,
            'A visitor wrote to you',
            [$message->message],
            array_filter(['Name' => $message->name, 'Email' => $message->email, 'Phone' => $message->phone, 'Subject' => $message->subject]),
        ));
    }
}
