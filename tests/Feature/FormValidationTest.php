<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_valid_name_and_indian_mobile_number(): void
    {
        $response = $this->post(route('register.send'), [
            'name' => '',
            'phone' => '12345',
            'sponsor_code' => 'NONEXISTENT',
        ]);

        $response->assertSessionHasErrors(['name', 'phone', 'sponsor_code']);
    }

    public function test_registration_succeeds_with_valid_details(): void
    {
        $response = $this->post(route('register.send'), [
            'name' => 'Valid User',
            'phone' => '9876543210',
            'email' => 'valid@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('register.verify'));
    }

    public function test_login_rejects_malformed_phone(): void
    {
        $response = $this->post(route('login.send'), [
            'phone' => 'invalid-phone',
        ]);

        $response->assertSessionHasErrors(['phone']);
    }

    public function test_member_bank_details_validation(): void
    {
        $member = Member::create([
            'member_code' => 'BSW9999',
            'name' => 'Bank Tester',
            'phone' => '9988776655',
            'status' => 'active',
        ]);

        // Malformed bank details
        $response = $this->actingAs($member, 'member')->put(route('member.wallet.bank-details'), [
            'bank_account_name' => '',
            'bank_name' => '',
            'bank_account_number' => 'letters_not_digits',
            'bank_ifsc' => 'INVALID_IFSC',
            'upi_id' => 'not-a-upi-id',
        ]);

        $response->assertSessionHasErrors([
            'bank_account_name',
            'bank_name',
            'bank_account_number',
            'bank_ifsc',
            'upi_id',
        ]);

        // Valid bank details
        $validResponse = $this->actingAs($member, 'member')->put(route('member.wallet.bank-details'), [
            'bank_account_name' => 'Bank Tester',
            'bank_name' => 'HDFC Bank',
            'bank_account_number' => '50100234567890',
            'bank_ifsc' => 'HDFC0001234',
            'upi_id' => 'tester@okhdfcbank',
        ]);

        $validResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'bank_ifsc' => 'HDFC0001234',
            'upi_id' => 'tester@okhdfcbank',
        ]);
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => '',
            'email' => 'invalid-email',
            'phone' => '1234',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'phone', 'message']);
    }

    public function test_direct_registration_without_sponsor_places_under_admin_root(): void
    {
        $root = Member::create([
            'member_code' => 'BS100001',
            'name' => 'Company Admin',
            'phone' => '9820011000',
            'status' => 'active',
        ]);

        $otpService = app(OtpService::class);
        $issue = $otpService->issue('9876543210', 'register');

        $sessionData = [
            'name' => 'Direct Registrant',
            'phone' => '9876543210',
            'email' => 'direct@example.com',
            'sponsor_code' => null,
        ];

        $response = $this->withSession(['pending_registration' => $sessionData])
            ->post(route('register.verify.submit'), [
                'otp' => $issue['debug_code'],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('member.dashboard'));

        $newMember = Member::where('phone', '9876543210')->first();
        $this->assertNotNull($newMember);
        $this->assertEquals($root->id, $newMember->sponsor_id);
        $this->assertEquals($root->id, $newMember->placement_id);
    }
}
