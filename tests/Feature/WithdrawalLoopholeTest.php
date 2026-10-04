<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Member;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * walletBalance() only reflects ledger entries, and a withdrawal only posts a debiting ledger
 * entry once it's actually PAID (WithdrawalService::markPaid). Between "requested" and "paid" —
 * which can be hours or days, waiting on admin approval and dispatch — that money is still
 * sitting in walletBalance(). request() must reserve it the moment a request is made, or a
 * member can request the same balance multiple times before any of them are paid.
 */
class WithdrawalLoopholeTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private WithdrawalService $withdrawals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = Member::create([
            'member_code' => 'BSWD0001', 'name' => 'Withdrawal Tester', 'phone' => '9123456780', 'status' => 'active',
            'bank_account_name' => 'Withdrawal Tester', 'bank_account_number' => '1234567890', 'bank_ifsc' => 'TEST0001234',
        ]);

        LedgerEntry::create([
            'member_id' => $this->member->id, 'type' => 'self', 'amount' => 10000, 'description' => 'Seed balance',
        ]);

        $this->withdrawals = app(WithdrawalService::class);
    }

    public function test_a_second_withdrawal_request_cannot_exceed_the_balance_left_after_the_first_pending_request(): void
    {
        $this->assertSame(10000.0, $this->member->walletBalance());

        // First request reserves the full balance.
        $this->withdrawals->request($this->member, 10000);

        // Neither has been paid yet, so walletBalance() is still 10000 — but none of it is
        // actually free, because the first request already claims all of it.
        $this->expectException(\RuntimeException::class);
        $this->withdrawals->request($this->member, 10000);
    }

    public function test_multiple_small_pending_requests_cannot_together_exceed_the_wallet_balance(): void
    {
        $this->withdrawals->request($this->member, 6000);
        $this->withdrawals->request($this->member, 3000);

        // 6000 + 3000 + 2000 = 11000 > the member's actual 10000 balance.
        $this->expectException(\RuntimeException::class);
        $this->withdrawals->request($this->member, 2000);
    }

    public function test_available_balance_frees_up_again_after_a_pending_request_is_rejected(): void
    {
        $withdrawal = $this->withdrawals->request($this->member, 10000);
        $withdrawal->update(['status' => 'rejected']);

        // Rejected no longer reserves anything, so the full balance should be requestable again.
        $second = $this->withdrawals->request($this->member, 10000);
        $this->assertSame(10000.0, (float) $second->amount);
    }
}
