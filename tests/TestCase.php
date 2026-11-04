<?php

namespace Tests;

use App\Support\CompensationSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The audit log remembers whether the newer columns exist; a test that rebuilds the table must not leak that.
        \App\Models\AuditLog::forgetSchemaCache();
    }

    /**
     * Starts a region the way the admin does (Regions page, "Create the root member"): the root is created for them,
     * because nobody can become the root by signing up. Defaults to the Main region.
     */
    protected function startRegion(?\App\Models\Region $region = null, string $name = 'Region Root', ?string $phone = null, ?string $email = null): \App\Models\Member
    {
        $region ??= \App\Models\Region::where('code', 'MAIN')->firstOrFail();
        $phone ??= '6'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);

        return app(\App\Services\MemberService::class)->createRoot($region, $name, $phone, $email);
    }

    /**
     * Saves a complete set of compensation settings, as an admin does by pressing Save on the Settings page. A cycle
     * cannot be created without them, so every test that runs a cycle calls this first and then overrides what it needs.
     *
     * @param  array<string, mixed>  $overrides  setting key => value; "levels" sets the level percentages
     */
    protected function configureCompensation(array $overrides = []): void
    {
        CompensationSettings::saveDefaults($overrides);
    }
}
