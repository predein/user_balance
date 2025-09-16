<?php

namespace App\Jobs;

use App\Enums\BalanceLogStatus;
use App\Enums\TransferStatus;
use App\Models\BalanceLog;
use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;

class TransferBalance implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public string $transferUuid,
        public int $fromUserId,
        public int $toUserId,
        public int $amountMicros,
        public int $currencyId,
    ) {}

    public function middleware(): array
    {
        return [
            new WithoutOverlapping("user_balance:{$this->fromUserId}:{$this->currencyId}"),
            new WithoutOverlapping("user_balance:{$this->toUserId}:{$this->currencyId}"),
        ];
    }

    public function handle(): void
    {
        try {
            /** @var Transfer $transfer */
            $transfer = Transfer::create(
                [
                    'transfer_uuid'  => $this->transferUuid,
                    'from_user_id' => $this->fromUserId,
                    'to_user_id' => $this->toUserId,
                    'currency_id' => $this->currencyId,
                    'balance_micros' => $this->amountMicros,
                    'status' => TransferStatus::PENDING,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $debitUuid = (string) Str::uuid();
        SubBalance::dispatchSync(
            $debitUuid,
            $this->fromUserId,
            $this->amountMicros,
            $this->currencyId,
        );
        if ($this->isRejected($debitUuid)) {
            $transfer->markFailed('insufficient funds');
            return;
        }

        $transfer->status = TransferStatus::DEBITED;
        $transfer->save();

        $creditUuid = (string) Str::uuid();
        try {
            AddBalance::dispatchSync(
                $creditUuid,
                $this->toUserId,
                $this->amountMicros,
                $this->currencyId,
            );
        } catch (\Throwable $e) {
            $this->compensateDebit($debitUuid);
            $transfer->markFailed('credit failed');
            throw $e;
        }

        // success
        $transfer->status = TransferStatus::SUCCEEDED;
        $transfer->save();
    }

    private function isRejected(string $opUuid): bool
    {
        return BalanceLog::where('operation_uuid', $opUuid)
            ->where('status', BalanceLogStatus::REJECTED)
            ->exists();
    }

    private function compensateDebit(string $debitUuid): void
    {
        $log = BalanceLog::where('operation_uuid', $debitUuid)->first();
        if (!$log) return; // неуспели зафиксировать — нечего компенсировать

        $compensateUuid = (string) Str::uuid();
        AddBalance::dispatchSync(
            $compensateUuid,
            $log->user_id,
            abs($log->balance_micros),
            $log->currency_id
        );
    }
}
