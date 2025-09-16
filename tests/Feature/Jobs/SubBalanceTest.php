<?php

namespace Tests\Feature\Jobs;

use App\Enums\BalanceLogStatus;
use App\Jobs\SubBalance;
use App\Models\BalanceLog;
use App\Models\UserBalance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class SubBalanceTest extends TestCase
{
    use RefreshDatabase;

    private int $userId = 2002;
    private int $currencyId = 840; // USD (пример)
    private int $amountMicros = 1_000_000; // 1.0

    public function test_decrements_existing_balance(): void
    {
        // Исходный баланс 3.0
        UserBalance::create([
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => 3_000_000,
        ]);

        $job = new SubBalance(
            'sub-001',
            $this->userId,
            $this->amountMicros,
            $this->currencyId,
        );

        /** @var DatabaseManager $db */
        $db = $this->app->make(DatabaseManager::class);
        $job->handle($db);

        // баланс уменьшен до 2.0
        $this->assertDatabaseHas('user_balances', [
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => 2_000_000,
        ]);

        // лог создан со статусом SUCCEEDED
        $this->assertDatabaseHas('balance_logs', [
            'operation_uuid' => 'sub-001',
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => -$this->amountMicros,
            'status'         => BalanceLogStatus::SUCCEEDED->value,
        ]);
    }

    public function test_is_idempotent_by_operation_uuid(): void
    {
        UserBalance::create([
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => 5_000_000,
        ]);

        $job1 = new SubBalance('sub-002', $this->userId, $this->amountMicros, $this->currencyId);
        $job2 = new SubBalance('sub-002', $this->userId, $this->amountMicros, $this->currencyId);

        $db = $this->app->make(DatabaseManager::class);

        $job1->handle($db);
        $job2->handle($db); // должен отработать идемпотентно (без повторного списания и без исключений)

        $balance = UserBalance::where('user_id', $this->userId)
            ->where('currency_id', $this->currencyId)
            ->firstOrFail();

        $this->assertSame(4_000_000, $balance->balance_micros, 'Balance must be decremented exactly once.');
        $this->assertSame(1, BalanceLog::where('operation_uuid', 'sub-002')->count(), 'Only one log row expected.');
    }

    public function test_fails_gracefully_when_insufficient_funds(): void
    {
        // Баланс меньше суммы списания
        UserBalance::create([
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => 300_000, // 0.3
        ]);

        $job = new SubBalance(
            'sub-003',
            $this->userId,
            $this->amountMicros, // 1.0
            $this->currencyId,
        );

        $db = $this->app->make(DatabaseManager::class);

        // Предполагаемое поведение: джоб не бросает исключение наружу,
        // помечает лог как REJECTED и НЕ меняет баланс
        $job->handle($db);

        // баланс не изменился
        $this->assertDatabaseHas('user_balances', [
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => 300_000,
        ]);

        // лог присутствует и имеет статус REJECTED
        $this->assertDatabaseHas('balance_logs', [
            'operation_uuid' => 'sub-003',
            'user_id'        => $this->userId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => -$this->amountMicros,
            'status'         => BalanceLogStatus::REJECTED->value,
        ]);

        $this->assertSame(1, BalanceLog::where('operation_uuid', 'sub-003')->count());
    }

    public function test_middleware_has_expected_key(): void
    {
        $job = new SubBalance('sub-004', $this->userId, $this->amountMicros, $this->currencyId);
        $middlewares = $job->middleware();

        $this->assertNotEmpty($middlewares);
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);

        // Слабая проверка ключа, аналогично AddBalanceTest
        $this->assertStringContainsString(
            "user_balance:{$this->userId}:{$this->currencyId}",
            serialize($middlewares[0])
        );
    }
}
