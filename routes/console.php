<?php

use Illuminate\Support\Facades\Artisan;
use App\Enums\Currency;
use App\Jobs\AddBalance;
use App\Jobs\SubBalance;
use App\Jobs\TransferBalance;
use App\Jobs\HoldBalance;
use App\Jobs\ReleaseBalance;
use App\Jobs\CaptureBalance;
use Illuminate\Support\Str;

Artisan::command('money:add {userId} {amount} {currencyISO}', function () {
    $userId = (int) $this->argument('userId');
    $amount = (float) $this->argument('amount');
    $amountMicros = (int) round($amount * 1_000_000);
    $currencyIso = (string) $this->argument('currencyISO');
    $currency = Currency::getByISO($currencyIso);
    $operationUuid = (string) Str::uuid();
    AddBalance::dispatch($operationUuid, $userId, $amountMicros, $currency->value);
    $this->info("Queued " . $operationUuid);
})->purpose('Money sub {userId} {amount} {currencyISO}');

Artisan::command('money:sub {userId} {amount} {currencyISO}', function () {
    $userId = (int) $this->argument('userId');
    $amount = (float) $this->argument('amount');
    $amountMicros = (int) round($amount * 1_000_000);
    $currencyIso = (string) $this->argument('currencyISO');
    $currency = Currency::getByISO($currencyIso);
    $operationUuid = (string) Str::uuid();
    SubBalance::dispatch($operationUuid, $userId, $amountMicros, $currency->value);
    $this->info("Queued " . $operationUuid);
})->purpose('Money sub {userId} {amount} {currencyISO}');

Artisan::command('money:check {uuid}', function () {
    $operationUuid = (string) $this->argument('uuid');
    $balanceLog = \App\Models\BalanceLog::query()->where('operation_uuid', $operationUuid)->first();
    if ($balanceLog) {
        $this->info("Operation " . $balanceLog->status . " " . $balanceLog->reason);
    } else {
        $this->info("Operation " . \App\Enums\BalanceLogStatus::PENDING->value);
    }
})->purpose('Money operation check {uuid}');

Artisan::command('money:hold {userId} {amount} {currencyISO}', function () {
    $userId = (int) $this->argument('userId');
    $amount = (float) $this->argument('amount');
    $amountMicros = (int) round($amount * 1_000_000);
    $currencyIso = (string) $this->argument('currencyISO');
    $currency = Currency::getByISO($currencyIso);
    $uuid = (string) Str::uuid();
    HoldBalance::dispatch($uuid, $userId, $amountMicros, $currency->value);
    $this->info("Queued " . $uuid);
})->purpose('Money hold {userId} {amount} {currencyISO}');

Artisan::command('money:release {uuid}', function () {
    $uuid = (string) $this->argument('uuid');
    ReleaseBalance::dispatch($uuid);
    $this->info("Queued " . $uuid);
})->purpose('Money release {uuid}');

Artisan::command('money:capture {uuid}', function () {
    $uuid = (string) $this->argument('uuid');
    CaptureBalance::dispatch($uuid);
    $this->info("Queued " . $uuid);
})->purpose('Money capture {uuid}');

Artisan::command('money:transfer {userIdFrom} {userIdTo} {amount} {currencyISO}', function () {
    $userIdFrom = (int) $this->argument('userIdFrom');
    $userIdTo   = (int) $this->argument('userIdTo');
    $amount = (float) $this->argument('amount');
    $amountMicros = (int) round($amount * 1_000_000);
    $currencyIso = (string) $this->argument('currencyISO');
    $currency = Currency::getByISO($currencyIso);
    $transferUuid = (string) Str::uuid();
    TransferBalance::dispatch($transferUuid, $userIdFrom, $userIdTo, $amountMicros, $currency->value);
    $this->info("Queued " . $transferUuid);
})->purpose('Money transfer {userIdFrom} {userIdTo} {amount} {currencyISO}');

Artisan::command('money:transfer_check {uuid}', function () {
    $transferUuid = (string) $this->argument('uuid');
    $transfer = \App\Models\Transfer::query()->where('transfer_uuid', $transferUuid)->first();
    if ($transfer) {
        $this->info("Transfer " . $transfer->status . " " . $transfer->reason);
    } else {
        $this->info("Transfer " . \App\Enums\TransferStatus::PENDING->value);
    }
})->purpose('Transfer check {uuid}');

