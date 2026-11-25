<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_closed_by_default(): void
    {
        $this->get('/register')->assertStatus(404);
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'x@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertStatus(404);

        $this->get('/login')->assertOk()->assertDontSee('Daftar Akun Baru');
        $this->assertDatabaseMissing('users', ['email' => 'x@gmail.com']);
    }

    public function test_registration_works_with_correct_code(): void
    {
        config(['nursery.registration_code' => 'KodeKebun2026']);

        $this->get('/login')->assertSee('Daftar Akun Baru');
        $this->get('/register')->assertOk()
            ->assertSee('name="registration_code"', false)
            ->assertDontSee('KodeKebun2026');

        $this->post('/register', [
            'registration_code' => '  KodeKebun2026 ', // spasi dari copy-paste WhatsApp dibuang
            'name' => 'Operator',
            'email' => 'Op@Gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        // Disimpan huruf kecil agar login tidak bergantung pada huruf besar/kecil
        $this->assertDatabaseHas('users', ['email' => 'op@gmail.com']);
        $this->assertAuthenticated();
    }

    public function test_registration_rejects_wrong_or_missing_code(): void
    {
        config(['nursery.registration_code' => 'KodeKebun2026']);
        User::factory()->create(['email' => 'sudahada@gmail.com']);

        $valid = [
            'name' => 'Penyusup',
            'email' => 'penyusup@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];

        $this->post('/register', $valid)
            ->assertSessionHasErrors('registration_code');

        foreach (['kodekebun2026', 'KodeKebun', 'KodeKebun2026x'] as $wrong) {
            $this->post('/register', [...$valid, 'registration_code' => $wrong])
                ->assertSessionHasErrors(['registration_code' => 'Kode pendaftaran salah. Minta kode ke admin nursery.']);
        }

        // Tanpa kode yang benar, tidak bocor info apakah email sudah terdaftar
        $this->post('/register', [...$valid, 'registration_code' => 'salah123', 'email' => 'sudahada@gmail.com'])
            ->assertSessionHasErrors('registration_code')
            ->assertSessionDoesntHaveErrors('email');

        // Kode yang diketik tidak disimpan ke session (old input)
        $this->post('/register', [...$valid, 'registration_code' => 'salah123']);
        $this->assertNull(session()->getOldInput('registration_code'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'penyusup@gmail.com']);
    }

    public function test_short_registration_code_keeps_registration_closed(): void
    {
        $original = [$_SERVER['REGISTRATION_CODE'] ?? null, $_ENV['REGISTRATION_CODE'] ?? null];

        try {
            foreach (['' => null, 'pendek' => null, '1234567' => null, '  KodeKebun2026  ' => 'KodeKebun2026'] as $env => $expected) {
                $_SERVER['REGISTRATION_CODE'] = $_ENV['REGISTRATION_CODE'] = (string) $env;
                $this->assertSame($expected, (require config_path('nursery.php'))['registration_code'], "REGISTRATION_CODE='{$env}'");
            }
        } finally {
            [$server, $envConst] = $original;
            if ($server === null) unset($_SERVER['REGISTRATION_CODE']); else $_SERVER['REGISTRATION_CODE'] = $server;
            if ($envConst === null) unset($_ENV['REGISTRATION_CODE']); else $_ENV['REGISTRATION_CODE'] = $envConst;
        }
    }

    public function test_registration_rejects_non_gmail_and_weak_input(): void
    {
        config(['nursery.registration_code' => 'KodeKebun2026']);
        $code = ['registration_code' => 'KodeKebun2026'];

        $this->post('/register', [
            ...$code,
            'name' => 'Operator',
            'email' => 'op@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->post('/register', [
            ...$code,
            'name' => 'Operator',
            'email' => 'op@gmail.com',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        $this->post('/register', [
            ...$code,
            'name' => str_repeat('a', 101),
            'email' => 'op@gmail.com',
            'password' => str_repeat('p', 73),
            'password_confirmation' => str_repeat('p', 73),
        ])->assertSessionHasErrors(['name', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_only_accepts_gmail_address(): void
    {
        User::factory()->create(['email' => 'admin@nursery.com', 'password' => 'PasswordKuat99']);

        $this->post('/login', ['email' => 'admin@nursery.com', 'password' => 'PasswordKuat99'])
            ->assertSessionHasErrors(['email' => 'Email harus menggunakan alamat @gmail.com.']);

        // Akhiran "gmail.com" tanpa "@" tepat di depannya bukan alamat Gmail
        $this->post('/login', ['email' => 'admin@notgmail.com', 'password' => 'PasswordKuat99'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_with_gmail_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'nama@gmail.com', 'password' => 'PasswordKuat99']);

        $this->post('/login', ['email' => '  Nama@Gmail.COM ', 'password' => 'PasswordKuat99'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_overlong_input(): void
    {
        $this->post('/login', ['email' => str_repeat('a', 250) . '@gmail.com', 'password' => 'x'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => 'nama@gmail.com', 'password' => str_repeat('p', 256)])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_login_page_restricts_inputs_in_browser(): void
    {
        $this->get('/login')
            ->assertSee('pattern="[^@\s]+@gmail\.com"', false)
            ->assertSee('placeholder="nama@gmail.com"', false)
            ->assertSee('title="Gunakan alamat @gmail.com"', false)
            ->assertSee('maxlength="255"', false)
            ->assertDontSee('{{', false);
    }

    public function test_seeder_uses_admin_password_from_env(): void
    {
        config(['nursery.admin_email' => 'admin.test@gmail.com', 'nursery.admin_password' => 'PasswordKuat99']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin.test@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('PasswordKuat99', $admin->password));
    }

    public function test_seeder_never_uses_old_default_password(): void
    {
        config(['nursery.admin_email' => 'admin.test@gmail.com', 'nursery.admin_password' => null]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin.test@gmail.com')->firstOrFail();
        $this->assertFalse(Hash::check('admin123', $admin->password));
    }

    public function test_seeder_requires_gmail_admin_email(): void
    {
        foreach ([null, 'admin@nursery.com'] as $email) {
            config(['nursery.admin_email' => $email]);

            try {
                $this->seed(DatabaseSeeder::class);
                $this->fail("Seeder seharusnya menolak ADMIN_EMAIL " . var_export($email, true));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('ADMIN_EMAIL', $e->getMessage());
            }
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_command_resets_existing_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@gmail.com']);

        $this->artisan('nursery:user', ['email' => 'admin@gmail.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'PasswordBaru1')
            ->expectsQuestion('Ulangi password', 'PasswordBaru1')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('PasswordBaru1', $user->fresh()->password));
    }

    public function test_user_command_creates_new_account(): void
    {
        $this->artisan('nursery:user', ['email' => 'Budi@Gmail.com', '--name' => 'Budi'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'PasswordBudi1')
            ->expectsQuestion('Ulangi password', 'PasswordBudi1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'budi@gmail.com', 'name' => 'Budi']);
    }

    public function test_user_command_rejects_non_gmail_account(): void
    {
        $this->artisan('nursery:user', ['email' => 'budi@nursery.com'])
            ->expectsOutput('Email harus menggunakan alamat @gmail.com.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_command_rejects_short_or_mismatched_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@gmail.com']);
        $oldHash = $user->password;

        $this->artisan('nursery:user', ['email' => 'admin@gmail.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'pendek')
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'admin@gmail.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', str_repeat('p', 73))
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'admin@gmail.com'])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'PasswordBaru1')
            ->expectsQuestion('Ulangi password', 'PasswordLain1')
            ->assertExitCode(1);

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_user_command_moves_old_account_to_gmail(): void
    {
        $user = User::factory()->create(['email' => 'admin@nursery.com', 'password' => 'PasswordKuat99']);

        $this->artisan('nursery:user', ['email' => 'admin@nursery.com', '--email-baru' => 'Nama@Gmail.com'])
            ->assertExitCode(0);

        // Akun yang sama (id tetap), jadi riwayat aktivitasnya tetap terhubung
        $this->assertSame('nama@gmail.com', $user->fresh()->email);

        $this->post('/login', ['email' => 'nama@gmail.com', 'password' => 'PasswordKuat99'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_command_rejects_invalid_new_email(): void
    {
        $user = User::factory()->create(['email' => 'admin@nursery.com']);
        User::factory()->create(['email' => 'dipakai@gmail.com']);

        $this->artisan('nursery:user', ['email' => 'admin@nursery.com', '--email-baru' => 'admin@yahoo.com'])
            ->expectsOutput('Email harus menggunakan alamat @gmail.com.')
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'admin@nursery.com', '--email-baru' => 'dipakai@gmail.com'])
            ->expectsOutput('Email baru sudah dipakai akun lain.')
            ->assertExitCode(1);

        $this->artisan('nursery:user', ['email' => 'tidakada@nursery.com', '--email-baru' => 'baru@gmail.com'])
            ->assertExitCode(1);

        $this->assertSame('admin@nursery.com', $user->fresh()->email);
    }
}
