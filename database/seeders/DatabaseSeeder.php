<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo accounts and menu data are only seeded on local and testing
     * environments. Production data must be provisioned explicitly, for
     * example via the `app:create-admin` command.
     */
    public function run(): void
    {
        if (! $this->allowsDemoData()) {
            return;
        }

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call(DemoMenuSeeder::class);
    }

    private function allowsDemoData(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
