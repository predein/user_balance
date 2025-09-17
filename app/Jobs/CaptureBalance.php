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
use App\Models\BalanceLog;
use App\Enums\BalanceLogStatus;

class CaptureBalance implements ShouldQueue
{
    use Queueable, Dispatchable;

    public function __construct(
        public string $holdUuid,
        public ?string $connectionName = null,
    ) {}

    public function handle(DatabaseManager $db): void
    {
        $dbConnection = $this->connectionName
            ? $db->connection($this->connectionName)
            : $db->connection();

        $dbConnection->transaction(function () {
            $hold = BalanceHold::where('hold_uuid', $this->holdUuid)->lockForUpdate()->first();
            if (!$hold || $hold->status !== HoldStatus::RESERVED) {
                return;
            }

            try {
                BalanceLog::create([
                    'operation_uuid' => $this->holdUuid, // one-to-one with hold capture
                    'user_id' => $hold->user_id,
                    'currency_id' => $hold->currency_id,
                    'balance_micros' => -$hold->amount_micros,
                    'status' => BalanceLogStatus::PENDING,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                return;
            }

            $balance = UserBalance::where('user_id', $hold->user_id)
                ->where('currency_id', $hold->currency_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($balance->reserved_micros < $hold->amount_micros) {
                BalanceLog::where('operation_uuid', $this->holdUuid)
                    ->update([
                        'status' => BalanceLogStatus::REJECTED,
                        'reason' => 'reserved_mismatch',
                    ]);
                throw new \RuntimeException('Reserved amount mismatch');
            }

            $balance->reserved_micros -= $hold->amount_micros;
            $balance->balance_micros -= $hold->amount_micros;
            $balance->save();

            BalanceLog::where('operation_uuid', $this->holdUuid)
                ->update(['status' => BalanceLogStatus::SUCCEEDED]);

            $hold->status = HoldStatus::CAPTURED;
            $hold->save();
        });
    }

    public function backoff(): array
    {
        return [5, 15, 60];
    }
}
