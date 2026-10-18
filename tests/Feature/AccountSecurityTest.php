<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_closed_by_default(): void
    {
        $this->get('/register')->assertStatus(404);
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'x@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertStatus(404);

        $this->get('/login')->assertOk()->assertDontSee('Daftar Akun Baru');
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_registration_works_when_enabled(): void
    {
        config(['nursery.allow_registration' => true]);

        $this->get('/login')->assertSee('Daftar Akun Baru');
        $this->post('/register', [
            'name' => 'Operator',
            'email' => 'op@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'op@example.com']);
    }

    public function test_seeder_uses_admin_password_from_env(): void
    {
        config(['nursery.admin_email' => 'admin@test.id', 'nursery.admin_password' => 'PasswordKuat99']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@test.id')->firstOrFail();
        $this->assertTrue(Hash::check('PasswordKuat99', $admin->password));
    }

    public function test_seeder_never_uses_old_default_password(): void
    {
        config(['nursery.admin_password' => null]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@nursery.com')->firstOrFail();
        $this->assertFalse(Hash::check('admin123', $admin->password));
    }

    public function test_user_command_resets_existing_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@nursery.com']);

        $this->artisan('nursery:user', ['email' => 'admin@nursery.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'PasswordBaru1')
            ->expectsQuestion('Ulangi password', 'PasswordBaru1')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('PasswordBaru1', $user->fresh()->password));
    }

    public function test_user_command_creates_new_account(): void
    {
        $this->artisan('nursery:user', ['email' => 'budi@nursery.com', '--name' => 'Budi'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'PasswordBudi1')
            ->expectsQuestion('Ulangi password', 'PasswordBudi1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'budi@nursery.com', 'name' => 'Budi']);
    }

    public function test_user_command_rejects_short_or_mismatched_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@nursery.com']);
        $oldHash = $user->password;

        $this->artisan('nursery:user', ['email' => 'admin@nursery.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'pendek')
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'admin@nursery.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'PasswordBaru1')
            ->expectsQuestion('Ulangi password', 'PasswordLain1')
            ->assertExitCode(1);

        $this->assertSame($oldHash, $user->fresh()->password);
    }
}
