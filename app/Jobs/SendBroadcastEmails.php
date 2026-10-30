<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MemberMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends an announcement or a "new plan" email to everyone it is meant for, a batch at a time, so a long member list
 * never blocks the page that asked for it. Needs a queue worker in production (php artisan queue:work).
 * Members who unsubscribed, or have no email address, are skipped.
 */
class SendBroadcastEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $kind, public int $id) {}

    public function handle(MemberMailer $mailer): void
    {
        // Region limits belong to the admin screens; this job addresses exactly who the announcement names.
        $members = Member::withoutGlobalScopes()->whereNotNull('email')->where('email', '!=', '')->where('email_opt_out', false);

        if ($this->kind === 'announcement') {
            $a = Announcement::find($this->id);
            if (! $a) {
                return;
            }
            $members->when($a->region_id, fn ($q) => $q->where('region_id', $a->region_id))
                ->when($a->audience === 'active', fn ($q) => $q->where('status', 'active'));
            $send = fn (Member $m) => $mailer->announcement($a, $m);
        } elseif ($this->kind === 'plan') {
            $plan = Plan::withoutGlobalScopes()->with('products')->find($this->id);
            if (! $plan || ! $plan->is_active) {
                return;
            }
            $members->where('region_id', $plan->region_id);
            $send = fn (Member $m) => $mailer->newPlan($plan, $m);
        } else {
            return;
        }

        $count = 0;
        $members->chunkById(200, function ($chunk) use ($send, &$count) {
            foreach ($chunk as $member) {
                $send($member);
                $count++;
            }
        });

        if ($this->kind === 'announcement') {
            Announcement::whereKey($this->id)->update(['emailed_count' => $count]);
        }
    }
}
