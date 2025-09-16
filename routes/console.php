<?php

use Illuminate\Support\Facades\Artisan;
use App\Enums\Currency;
use App\Jobs\AddBalance;
use App\Jobs\SubBalance;
use App\Jobs\TransferBalance;
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

//Artisan::command('money:hold {userId} {amount} {currencyISO}', function () {
//    $userId = (int) $this->argument('userId');
//    $amount = (float) $this->argument('amount');
//    $amountMicros = (int) round($amount * 1_000_000);
//    $currencyIso = (string) $this->argument('currencyISO');
//    $currency = Currency::getByISO($currencyIso);
//    $operationUuid = (string) Str::uuid();
//
//    HoldBalance::dispatch($operationUuid, $userId, $amountMicros, $currency->value);
//
//    $this->info("Queued");
//})->purpose('Money hold {userId} {amount} {currencyISO}');
//
//Artisan::command('money:unhold {userId} {uuid}', function () {
//    $userId = (int) $this->argument('userId');
//    $uuid = (string) $this->argument('uuid');
//
//    UnholdBalance::dispatch($operationUuid, $userId, $amountMicros, $currency->value);
//
//    $this->info("Queued");
//})->purpose('Money unhold {userId} {uuid}');
