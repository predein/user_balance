<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\UserBalance;
use App\Enums\Currency;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $userAlice = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);
        UserBalance::create([
            'user_id' => $userAlice->id,
            'balance_micros' => 1_500_000,
            'currency_id' => Currency::EUR,
        ]);
        UserBalance::create([
            'user_id' => $userAlice->id,
            'balance_micros' => 1_500_000,
            'currency_id' => Currency::GBP,
        ]);

        $userBob = User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);
        UserBalance::create([
            'user_id' => $userBob->id,
            'balance_micros' => 2_000_000,
            'currency_id' => Currency::EUR,
        ]);
    }
}
