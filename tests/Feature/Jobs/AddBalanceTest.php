<?php

namespace Tests\Feature\Jobs;

use App\Enums\BalanceLogStatus;
use App\Jobs\AddBalance;
use App\Models\BalanceLog;
use App\Models\UserBalance;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class AddBalanceTest extends TestCase
{
    use RefreshDatabase;

    private int $userId = 1001;
    private int $currencyId = 978; // EUR
    private int $amountMicros = 1_500_000;

    public function test_creates_balance_and_log_if_not_exists(): void
    {
        $this->assertDatabaseMissing('users', [
            'id' => $this->userId,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $this->assertDatabaseMissing('user_balances', [
            'user_id' => $this->userId,
            'currency_id' => $this->currencyId,
        ]);

        $job = new AddBalance(
            'op-001',
            $this->userId,
            $this->amountMicros,
            $this->currencyId,
        );

        /** @var DatabaseManager $db */
        $db = $this->app->make(DatabaseManager::class);
        $job->handle($db);

        // баланс создан и увеличен
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->userId,
            'currency_id' => $this->currencyId,
            'balance_micros' => $this->amountMicros,
        ]);

        // лог создан со статусом SUCCEEDED
        $this->assertDatabaseHas('balance_logs', [
            'operation_uuid' => 'op-001',
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => $this->amountMicros,
            'status'         => BalanceLogStatus::SUCCEEDED->value,
        ]);
    }

    public function test_increments_existing_balance(): void
    {
        // исходное состояние
        UserBalance::create([
            'user_id' => $this->userId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 2_000_000,
        ]);

        $job = new AddBalance(
            'op-002',
            $this->userId,
            $this->amountMicros,
            $this->currencyId,
        );

        /** @var DatabaseManager $db */
        $db = $this->app->make(DatabaseManager::class);
        $job->handle($db);

        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->userId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 3_500_000, // 2.0 + 1.5
        ]);

        $this->assertDatabaseHas('balance_logs', [
            'operation_uuid' => 'op-002',
            'status' => BalanceLogStatus::SUCCEEDED->value,
        ]);
    }

    public function test_is_idempotent_by_operation_uuid(): void
    {
        $job1 = new AddBalance('op-003', $this->userId, $this->amountMicros, $this->currencyId);
        $job2 = new AddBalance('op-003', $this->userId, $this->amountMicros, $this->currencyId);

        /** @var DatabaseManager $db */
        $db = $this->app->make(DatabaseManager::class);

        $job1->handle($db);
        $job2->handle($db); // должен «мягко» выйти на Duplicate entry

        // баланс увеличен ровно один раз
        $balance = UserBalance::where('user_id', $this->userId)
            ->where('currency_id', $this->currencyId)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame($this->amountMicros, $balance->balance_micros);

        // создан только один лог
        $this->assertSame(1, BalanceLog::where('operation_uuid', 'op-003')->count());
    }

    public function test_middleware_has_expected_key(): void
    {
        $job = new AddBalance('op-004', $this->userId, $this->amountMicros, $this->currencyId);
        $middlewares = $job->middleware();

        $this->assertNotEmpty($middlewares);
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);

        $this->assertStringContainsString(
            "user_balance:{$this->userId}:{$this->currencyId}",
            serialize($middlewares[0])
        );
    }
}
