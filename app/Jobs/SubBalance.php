<?php

namespace App\Jobs;

use App\Enums\BalanceLogStatus;
use App\Models\BalanceLog;
use App\Models\UserBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SubBalance implements ShouldQueue
{
    use Queueable, Dispatchable;

    public function __construct(
        public string $operationUuid,
        public int $userId,
        public int $amountMicros,
        public int $currencyId,
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
        $db->connection()->transaction(function () {
            $balance = UserBalance::query()
                ->where('user_id', $this->userId)
                ->where('currency_id', $this->currencyId)
                ->lockForUpdate()
                ->first();

            try {
                $log = BalanceLog::create(
                    [
                        'operation_uuid' => $this->operationUuid,
                        'user_id'        => $this->userId,
                        'currency_id'    => $this->currencyId,
                        'balance_micros' => -$this->amountMicros,
                        'status'         => BalanceLogStatus::PENDING,
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                return;
            }

            if (!$balance || $balance->balance_micros < $this->amountMicros) {
                $log->status = BalanceLogStatus::REJECTED;
                $log->reason = 'insufficient funds ';
                $log->save();
                return;
            }

            $balance->balance_micros -= $this->amountMicros;
            $balance->save();

            $log->status = BalanceLogStatus::SUCCEEDED;
            $log->save();
        });
    }

    public function backoff(): array
    {
        return [5, 15, 60];
    }
}
