<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxParameterSeeder::class,
            CategorySeeder::class,
        ]);

        // Konto logowania z .env (ADMIN_EMAIL, ADMIN_PASSWORD); można też użyć `php artisan make:filament-user`.
        if ($email = env('ADMIN_EMAIL')) {
            User::updateOrCreate(['email' => $email], [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => env('ADMIN_PASSWORD') ?: throw new \RuntimeException('Ustaw ADMIN_PASSWORD w .env'),
            ]);
        }
    }
}
