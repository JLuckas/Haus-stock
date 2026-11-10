<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(StockDemoSeeder::class);

        // Local development login only. In production create accounts with `php artisan user:create`.
        if (app()->isLocal()) {
            User::query()->updateOrCreate(
                ['email' => 'admin@haus.test'],
                ['name' => 'Haus Admin', 'password' => 'password', 'email_verified_at' => now()],
            );
        }
    }
}
