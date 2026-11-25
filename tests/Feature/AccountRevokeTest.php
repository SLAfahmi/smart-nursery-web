<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountRevokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_command_blocks_login_but_keeps_account_and_history(): void
    {
        $user = User::factory()->create(['email' => 'budi@gmail.com', 'password' => 'PasswordBudi1']);
        $user->activityLogs()->create(['action' => 'Login Ke Sistem']);

        $this->artisan('nursery:user', ['email' => 'Budi@Gmail.com', '--cabut' => true])
            ->expectsOutputToContain('Akses budi@gmail.com dicabut')
            ->expectsOutputToContain('REGISTRATION_CODE')
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->isDisabled());
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'Login Ke Sistem']);

        $this->post('/login', ['email' => 'budi@gmail.com', 'password' => 'PasswordBudi1'])
            ->assertSessionHasErrors(['email' => EnsureAccountActive::MESSAGE]);
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'Login Ditolak (Akun Nonaktif)']);
    }

    public function test_wrong_password_on_revoked_account_does_not_reveal_status(): void
    {
        User::factory()->create(['email' => 'budi@gmail.com', 'password' => 'PasswordBudi1', 'disabled_at' => now()]);

        $this->post('/login', ['email' => 'budi@gmail.com', 'password' => 'salahsalah'])
            ->assertSessionHasErrors(['email' => 'Email atau password yang Anda masukkan salah.']);
        $this->assertGuest();
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Login Ditolak (Akun Nonaktif)']);
    }

    public function test_logged_in_user_is_kicked_out_after_revoke(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['disabled_at' => now()])->save();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => EnsureAccountActive::MESSAGE]);
        $this->assertGuest();
    }

    public function test_dashboard_polling_gets_401_after_revoke(): void
    {
        $user = User::factory()->create(['disabled_at' => now()]);

        $this->actingAs($user)
            ->getJson('/api/dashboard/status')
            ->assertStatus(401)
            ->assertJson(['status' => 'error', 'message' => EnsureAccountActive::MESSAGE]);
        $this->assertGuest();

        $this->actingAs($user)
            ->postJson('/api/toggle-relay', ['relay' => 'main', 'on' => true])
            ->assertStatus(401);
    }

    public function test_restore_command_allows_login_again_with_old_password(): void
    {
        $user = User::factory()->create(['email' => 'budi@gmail.com', 'password' => 'PasswordBudi1', 'disabled_at' => now()]);

        $this->artisan('nursery:user', ['email' => 'budi@gmail.com', '--pulihkan' => true])
            ->expectsOutputToContain('dipulihkan')
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->isDisabled());
        $this->post('/login', ['email' => 'budi@gmail.com', 'password' => 'PasswordBudi1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_revoke_and_restore_are_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'budi@gmail.com']);

        $this->artisan('nursery:user', ['email' => 'budi@gmail.com', '--pulihkan' => true])
            ->expectsOutputToContain('sudah aktif')
            ->assertExitCode(0);

        $this->artisan('nursery:user', ['email' => 'budi@gmail.com', '--cabut' => true])->assertExitCode(0);
        $revokedAt = $user->fresh()->disabled_at;

        $this->travel(1)->hours();
        $this->artisan('nursery:user', ['email' => 'budi@gmail.com', '--cabut' => true])
            ->expectsOutputToContain('sudah dicabut sejak')
            ->assertExitCode(0);
        // Waktu pencabutan pertama tidak tertimpa
        $this->assertEquals($revokedAt, $user->fresh()->disabled_at);
    }

    public function test_revoke_command_rejects_unknown_account_and_conflicting_options(): void
    {
        $user = User::factory()->create(['email' => 'budi@gmail.com']);

        $this->artisan('nursery:user', ['email' => 'tidakada@gmail.com', '--cabut' => true])
            ->expectsOutput('Akun tidakada@gmail.com tidak ditemukan.')
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'budi@gmail.com', '--cabut' => true, '--pulihkan' => true])
            ->expectsOutputToContain('Pilih salah satu saja')
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'budi@gmail.com', '--cabut' => true, '--email-baru' => 'lain@gmail.com'])
            ->assertExitCode(1);

        $this->assertFalse($user->fresh()->isDisabled());
        $this->assertSame('budi@gmail.com', $user->fresh()->email);
    }

    public function test_revoked_email_cannot_register_again(): void
    {
        config(['nursery.registration_code' => 'KodeKebun2026']);
        User::factory()->create(['email' => 'budi@gmail.com', 'disabled_at' => now()]);

        $this->post('/register', [
            'registration_code' => 'KodeKebun2026',
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_users_command_lists_accounts_with_status(): void
    {
        User::factory()->create(['name' => 'Admin', 'email' => 'admin@gmail.com']);
        User::factory()->create(['name' => 'Budi', 'email' => 'budi@gmail.com', 'disabled_at' => now()]);

        $this->assertSame(0, Artisan::call('nursery:users'));
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/admin@gmail\.com\s*\|\s*Aktif/', $output);
        $this->assertMatchesRegularExpression('/budi@gmail\.com\s*\|\s*Dicabut \d{2}-\d{2}-\d{4}/', $output);
    }
}
