
## User balance

Создан на Laravel с его очередями

## Особенности реализации
Предполагаем, что пользователи могут быть в разных базах.
Поэтому не используем общую транзакцию при трансфере.
Но делаем компенсационные начисления, если вторая операция не прошла.

Система следит за идемпотентностью событий
и RaceCondition (через механизм самой очереди + select for update)

Деньги храню в integer поле balance_micros. Это число умноженное на 1_000_000 (подглядел у GoggleWallet :)

Основной код в файлах
<pre>
app/Jobs/AddBalance.php
app/Jobs/SubBalance.php
app/Jobs/TransferBalance.php
</pre>

## Как запустить

Локально на компьютере необходимы PHP+MySql
- PHP 8.4.11
- MySql 9.4.0 

<pre>
git clone git@github.com:predein/user_balance.git
# отредактируйте .env: DB_*
composer install
# применяем миграции и сиды
php artisan migrate
php artisan db:seed
</pre>

в сидах создадутся 2 пользавателя user_ids: 1 и 2

### Отправка событий из консоли
<pre>
php artisan money:add {userId} {amount} {currencyISO}
php artisan money:sub {userId} {amount} {currencyISO}
php artisan money:transfer {userIdFrom} {userIdTo} {amount} {currencyISO}
</pre>

пример Add
<pre>
php artisan money:add 1 0.5 EUR
# Queued 1fe35b86-2f43-47c2-9c29-f84a42e1fec9
# обработать очередь:
php artisan queue:work --once

# команда создаст event и положит в очередь
# результат в базе:
select * from user_balance.user_balances;
select * from user_balance.balance_logs;

mysql> select * from balance_logs \G
*************************** 1. row ***************************
            id: 1
operation_uuid: 1fe35b86-2f43-47c2-9c29-f84a42e1fec9
       user_id: 1
   currency_id: 978
balance_micros: 500000
        status: succeeded
        reason: NULL
    created_at: 2025-09-17 00:20:43
    updated_at: 2025-09-17 00:20:43
1 row in set (0.000 sec)
</pre>

пример Transfer
<pre>
php artisan money:transfer 1 2 0.01 GBP
# Queued e7a50982-4a5b-4025-a496-a2523fa12e7e
php artisan queue:work --once

# результат в базе:
select * from user_balance.user_balances;
select * from user_balance.balance_logs;
select * from user_balance.transfers;

mysql> select * from balance_logs \G
*************************** 1. row ***************************
            id: 1
operation_uuid: 7d0e203f-5a6b-4064-bc54-c7243040403f
       user_id: 1
   currency_id: 826
balance_micros: -10000
        status: succeeded
        reason: NULL
    created_at: 2025-09-17 00:25:32
    updated_at: 2025-09-17 00:25:32
*************************** 2. row ***************************
            id: 2
operation_uuid: f260214b-682a-4a59-afac-202157ee11af
       user_id: 2
   currency_id: 826
balance_micros: 10000
        status: succeeded
        reason: NULL
    created_at: 2025-09-17 00:25:32
    updated_at: 2025-09-17 00:25:32
2 rows in set (0.001 sec)

mysql> select * from transfers \G
*************************** 1. row ***************************
            id: 1
 transfer_uuid: e7a50982-4a5b-4025-a496-a2523fa12e7e
  from_user_id: 1
    to_user_id: 2
balance_micros: 10000
   currency_id: 826
        status: succeeded
        reason: NULL
    created_at: 2025-09-17 00:25:32
    updated_at: 2025-09-17 00:25:32
1 row in set (0.001 sec)


</pre>


### Тесты
<pre>
tests/Feature/Jobs/AddBalanceTest.php
tests/Feature/Jobs/SubBalanceTest.php
tests/Feature/Jobs/TransferBalanceTest.php

#запуск тестов
php artisan test
</pre>

### Не сделал
- Hold/Unhold - надо немного доработать user_balances. Плюс можно доработать остальные операции с учетом hold.
- Transfer вызывает Sub и Add синхронно - это значит все должно выполняется на одном сервере. Альтернатива SAGA.
- Можно сделать какой-то интерфейс сообщающий результат операции (succeeded/rejected)

