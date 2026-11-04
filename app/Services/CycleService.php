<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\User;
use App\Support\CompensationSettings;
use Illuminate\Support\Facades\DB;

class CycleService
{
    public function __construct(private readonly CompensationEngine $engine) {}

    public function create(string $periodStart, string $periodEnd): Cycle
    {
        // A cycle posts real income, so it never runs on built-in defaults: the settings must have been saved on purpose.
        CompensationSettings::assertReady();

        return Cycle::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
        ]);
    }

    /**
     * Admin fully controls when this runs — nothing here happens on a timer. This is the
     * moment earned income is posted to the ledger (paying it OUT is a separate, later
     * dispatch step — see DispatchService).
     *
     * The cycle is re-read under a row lock inside one transaction, so two admins pressing Approve together (or a
     * stale browser tab) cannot both run it: the second one sees "approved" and stops before anything is posted.
     */
    public function approve(Cycle $cycle, User $admin): void
    {
        // Checked again here: the settings could have been changed since the draft was made.
        CompensationSettings::assertReady();

        // Ranks before the cycle, to tell people whose rank went up.
        $ranksBefore = \App\Models\Member::withoutGlobalScopes()->pluck('rank', 'id');

        DB::transaction(function () use ($cycle, $admin) {
            $locked = Cycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();

            if ($locked->isApproved()) {
                throw new \RuntimeException('This cycle has already been approved.');
            }

            $this->engine->run($locked);
            $locked->update(['approved_by' => $admin->id]);
        });

        $cycle->refresh();

        $mailer = app(MemberMailer::class);
        $mailer->incomeCredited($cycle);

        foreach (\App\Models\Member::withoutGlobalScopes()->whereNotNull('rank')->where('rank', '!=', 'none')->get() as $member) {
            if (($ranksBefore[$member->id] ?? null) !== $member->rank) {
                $mailer->rankReached($member, $member->rank);
            }
        }
    }
}
