<?php

use App\Enums\HoldStatus;
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
        Schema::create('balance_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('hold_uuid')->unique();
            $table->foreignId('user_id');
            $table->bigInteger('amount_micros');
            $table->unsignedSmallInteger('currency_id');
            $table->enum('status', HoldStatus::cases());
            $table->timestamps();

            $table->index(['user_id','currency_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_holds');
    }
};
