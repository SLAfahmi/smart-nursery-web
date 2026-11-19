<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Email harus memakai domain config('nursery.email_domain'), default gmail.com.
 * Dipakai login, register, `php artisan nursery:user`, dan seeder agar aturannya satu sumber.
 */
class AllowedEmailDomain implements ValidationRule
{
    public static function domain(): string
    {
        return Str::lower((string) config('nursery.email_domain', 'gmail.com'));
    }

    /** Aturan lengkap input email akun (login, register, `nursery:user`, seeder) */
    public static function rules(): array
    {
        return ['required', 'string', 'max:255', 'email', new self];
    }

    /** Nilai atribut `pattern` untuk <input type="email"> (browser hanya sebagai lapisan UX) */
    public static function htmlPattern(): string
    {
        return '[^@\s]+@' . preg_quote(self::domain());
    }

    /** Email dibandingkan dalam huruf kecil: Gmail tidak membedakan huruf besar/kecil */
    public static function normalize(mixed $email): mixed
    {
        return is_string($email) ? Str::lower(trim($email)) : $email;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Format email dicek rule 'email'. str_ends_with, bukan regex: tidak ada celah "$" yang menerima newline.
        if (!is_string($value) || !str_ends_with(Str::lower($value), '@' . self::domain())) {
            $fail('Email harus menggunakan alamat @' . self::domain() . '.');
        }
    }
}
