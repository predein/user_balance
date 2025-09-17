<?php

namespace App\Jobs;

use App\Enums\HoldStatus;
use App\Models\BalanceHold;
use App\Models\UserBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class HoldBalance implements ShouldQueue
{
    use Queueable, Dispatchable;
    public function __construct(
        public string $holdUuid,
        public int $userId,
        public int $amountMicros,
        public int $currencyId,
        public ?string $connectionName = null,
    ) {
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping("user_balance:{$this->userId}:{$this->currencyId}"),
        ];
    }

    public function handle(DatabaseManager $db): void
    {
        $dbConnection = $this->connectionName
            ? $db->connection($this->connectionName)
            : $db->connection();

        $dbConnection->transaction(function () {
            $balance = UserBalance::query()
                ->where('user_id', $this->userId)
                ->where('currency_id', $this->currencyId)
                ->lockForUpdate()
                ->first();

            try {
                $hold = BalanceHold::create(
                    [
                        'hold_uuid' => $this->holdUuid,
                        'user_id' => $this->userId,
                        'currency_id' => $this->currencyId,
                        'amount_micros' => $this->amountMicros,
                        'status' => HoldStatus::RELEASED,
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                return;
            }

            if (!$balance || $balance->balance_micros - $balance->reserved_micros < $this->amountMicros) {
                $hold->status = HoldStatus::RELEASED;
                $hold->save();
                return;
            }

            $balance->reserved_micros += $this->amountMicros;
            $balance->save();

            $hold->status = HoldStatus::RESERVED;
            $hold->save();
        });
    }

    public function backoff(): array
    {
        return [5, 15, 60];
    }
}
