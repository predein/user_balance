<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_balances', function (Blueprint $t) {
            $t->bigInteger('reserved_micros')->default(0)->after('balance_micros');
            $t->unique(['user_id','currency_id'], 'user_id_currency_id');
        });
    }
    public function down(): void
    {
        Schema::table('user_balances', function (Blueprint $t) {
            $t->dropColumn('reserved_micros');
            $t->dropUnique('user_id_currency_id');
        });
    }
};
