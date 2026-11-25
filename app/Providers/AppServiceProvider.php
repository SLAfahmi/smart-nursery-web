<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hosting meng-cache style.css sangat lama (max-age 10 tahun), jadi URL-nya diberi versi
        // (?v=waktu file diubah) agar browser langsung mengambil CSS baru setiap kali file di-upload.
        View::composer(['layouts.app', 'auth.*'], function ($view) {
            $css = public_path('css/style.css');
            $view->with('cssVersion', is_file($css) ? filemtime($css) : null);
        });
    }
}
