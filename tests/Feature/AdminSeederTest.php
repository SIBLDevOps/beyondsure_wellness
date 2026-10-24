<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin_from_the_configured_login(): void
    {
        config(['admin.name' => 'Owner', 'admin.email' => 'Owner@Company.com', 'admin.password' => 'password']);

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'owner@company.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('Owner', $admin->name);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_running_it_again_never_resets_an_existing_admins_password(): void
    {
        config(['admin.email' => 'owner@company.com', 'admin.password' => 'password']);
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'owner@company.com')->first();
        $admin->update(['password' => 'ChangedByOwner456']);

        config(['admin.password' => 'SomethingElse789']);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('ChangedByOwner456', $admin->fresh()->password), 'The seeder must not overwrite a changed password');
    }

    public function test_outside_local_it_needs_an_email_and_creates_nothing_without_one(): void
    {
        config(['admin.email' => null, 'admin.password' => null]);

        $this->seed(AdminSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_with_no_password_set_outside_local_a_random_one_is_generated(): void
    {
        config(['admin.email' => 'owner@company.com', 'admin.password' => null]);

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'owner@company.com')->first();
        $this->assertNotNull($admin);
        $this->assertFalse(Hash::check('password', $admin->password), 'Never fall back to a guessable password outside local');
    }
}
