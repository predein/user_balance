<?php

namespace Tests\Feature\Jobs;

use App\Enums\BalanceLogStatus;
use App\Enums\HoldStatus;
use App\Jobs\TransferBalance;
use App\Models\BalanceLog;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class TransferBalanceTest extends TestCase
{
    use RefreshDatabase;

    private int $fromUserId = 301;
    private int $toUserId   = 302;
    private int $currencyId = 826; // GBP
    private int $amountMicros = 1_200_000; // 1.2

    public function test_transfers_between_users_successfully(): void
    {
        User::forceCreate(['id' => $this->fromUserId, 'name' => '', 'email' => $this->fromUserId, 'password' => '']);
        User::forceCreate(['id' => $this->toUserId, 'name' => '', 'email' => $this->toUserId, 'password' => '']);

        // from = 3.0, to = 0.5
        UserBalance::create([
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 3_000_000,
        ]);
        UserBalance::create([
            'user_id' => $this->toUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 500_000,
        ]);

        $job = new TransferBalance(
            'tr-001',
            $this->fromUserId,
            $this->toUserId,
            $this->amountMicros,
            $this->currencyId,
        );

        $job->handle();

        // Балансы обновлены атомарно
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 1_800_000, // 3.0 - 1.2
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->toUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 1_700_000, // 0.5 + 1.2
        ]);

        // Созданы два лога операции
        $this->assertDatabaseHas('balance_logs', [
            'user_id'        => $this->fromUserId,
            'currency_id'    => $this->currencyId,
            'balance_micros' => -$this->amountMicros,
            'status'         => BalanceLogStatus::SUCCEEDED->value,
        ]);
        $this->assertDatabaseHas('balance_logs', [
            'user_id'        => $this->toUserId,
            'currency_id'    => $this->currencyId,
            'balance_micros' =>  $this->amountMicros,
            'status'         => BalanceLogStatus::SUCCEEDED->value,
        ]);
    }

    public function test_is_idempotent_by_operation_uuid(): void
    {
        User::forceCreate(['id' => $this->fromUserId, 'name' => '', 'email' => $this->fromUserId, 'password' => '']);
        User::forceCreate(['id' => $this->toUserId, 'name' => '', 'email' => $this->toUserId, 'password' => '']);

        UserBalance::create([
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 2_500_000,
        ]);
        UserBalance::create([
            'user_id' => $this->toUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 100_000,
        ]);

        $job1 = new TransferBalance('tr-002', $this->fromUserId, $this->toUserId, $this->amountMicros, $this->currencyId);
        $job2 = new TransferBalance('tr-002', $this->fromUserId, $this->toUserId, $this->amountMicros, $this->currencyId);

        $job1->handle();
        // Повтор — должен отработать без второго списания/зачисления и без исключений
        $job2->handle();

        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 1_300_000, // 2.5 - 1.2
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->toUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 1_300_000, // 0.1 + 1.2
        ]);

        // И ровно по одному логу на каждого участника
        $this->assertSame(1, BalanceLog::where([
            'user_id' => $this->fromUserId,
        ])->count());
        $this->assertSame(1, BalanceLog::where([
            'user_id' => $this->toUserId,
        ])->count());
    }

    public function test_fails_when_insufficient_funds_and_rolls_back(): void
    {
        User::forceCreate(['id' => $this->fromUserId, 'name' => '', 'email' => $this->fromUserId, 'password' => '']);
        User::forceCreate(['id' => $this->toUserId, 'name' => '', 'email' => $this->toUserId, 'password' => '']);

        // Недостаточно средств у отправителя
        UserBalance::create([
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 700_000,
        ]);
        UserBalance::create([
            'user_id' => $this->toUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 400_000,
        ]);

        $job = new TransferBalance('tr-003', $this->fromUserId, $this->toUserId, $this->amountMicros, $this->currencyId);

        $db = $this->app->make(DatabaseManager::class);
        $job->handle($db);

        // Балансы не изменились (откат)
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 700_000,
        ]);
        $this->assertDatabaseHas('user_balances', [
            'user_id' => $this->toUserId,
            'currency_id' => $this->currencyId,
            'balance_micros' => 400_000,
        ]);

        // Hold вернули
        $this->assertDatabaseHas('balance_holds', [
            'user_id' => $this->fromUserId,
            'currency_id' => $this->currencyId,
            'amount_micros' => $this->amountMicros,
            'status' => HoldStatus::RELEASED->value,
        ]);

        // Не должно быть успешного лога у получателя
        $this->assertSame(0, BalanceLog::where([
            'user_id' => $this->toUserId,
            'status' => BalanceLogStatus::SUCCEEDED->value,
        ])->count());
    }

    public function test_middleware_blocks_both_sides(): void
    {
        $job = new TransferBalance('tr-004', $this->fromUserId, $this->toUserId, $this->amountMicros, $this->currencyId);
        $middlewares = $job->middleware();

        // Предполагаем, что вы добавляете два WithoutOverlapping — по каждому участнику.
        $this->assertNotEmpty($middlewares);
        $this->assertGreaterThanOrEqual(1, count($middlewares));
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);

        $serialized = implode('|', array_map(static fn($m) => serialize($m), $middlewares));
        $this->assertStringContainsString("user_balance:{$this->fromUserId}:{$this->currencyId}", $serialized);
        $this->assertStringContainsString("user_balance:{$this->toUserId}:{$this->currencyId}", $serialized);
    }
}
