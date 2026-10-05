<?php

namespace Database\Seeders;

use App\Models\User;
use Identity\Domain\User\ValueObject\UserId;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'identity_user_id' => UserId::generate()->value(),
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
