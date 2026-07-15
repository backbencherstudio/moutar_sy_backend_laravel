<?php

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
       Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->string('transaction_number')->unique();
        $table->foreignId('user_id')->constrained()->onDelete('restrict');
        $table->string('reference_number')->nullable()->index();
        $table->foreignId('transfer_id')->nullable()->constrained()->onDelete('restrict');
        $table->enum('type', ['debit', 'credit'])->index();
        $table->enum('purpose', ['wallet_deposit', 'money_transfer', 'transfer_fee', 'transfer_refund', 'bonus', 'withdraw']);
        $table->decimal('amount', 15, 2);
        $table->string('currency', 3);
        $table->decimal('opening_balance', 15, 2);
        $table->decimal('closing_balance', 15, 2);
        $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
        $table->string('remarks')->nullable();
        $table->timestamps();

        $table->index(['user_id', 'type']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
