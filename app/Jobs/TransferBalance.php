<?php

namespace App\Jobs;

use App\Enums\BalanceLogStatus;
use App\Enums\HoldStatus;
use App\Enums\TransferStatus;
use App\Models\BalanceHold;
use App\Models\Transfer;
use App\Models\User;
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

        // предполагаем, что класс User автоматически определяет коннект по user_id
        $fromConnectionName = User::find($this->fromUserId)->getConnection()->getName();
        $toConnectionName = User::find($this->toUserId)->getConnection()->getName();

        $debitUuid = (string) Str::uuid();
        HoldBalance::dispatchSync(
            $debitUuid,
            $this->fromUserId,
            $this->amountMicros,
            $this->currencyId,
            $fromConnectionName,
        );
        if ($this->isRejected($debitUuid, $fromConnectionName)) {
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
                $toConnectionName,
            );
        } catch (\Throwable $e) {
            ReleaseBalance::dispatchSync($debitUuid, $fromConnectionName);
            $transfer->markFailed('credit failed');
            throw $e;
        }
        CaptureBalance::dispatchSync($debitUuid, $fromConnectionName);

        // success
        $transfer->status = TransferStatus::SUCCEEDED;
        $transfer->save();
    }

    private function isRejected(string $uuid, string $сonnectionName): bool
    {
        return !(
            BalanceHold::on($сonnectionName)
            ->where('hold_uuid', $uuid)
            ->where('status', HoldStatus::RESERVED)
            ->exists()
        );
    }
}
