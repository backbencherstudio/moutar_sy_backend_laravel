<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('gateway_balances', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name');
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->string('currency', 3);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('gateway_balances');
    }
};
