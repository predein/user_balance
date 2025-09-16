<?php

use App\Enums\BalanceLogStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('balance_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_uuid')->unique();
            $table->foreignId('user_id');
            $table->integer('currency_id');
            $table->bigInteger('balance_micros');
            $table->enum('status', BalanceLogStatus::cases());
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_logs');
    }
};
