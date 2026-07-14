<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')->constrained()->onDelete('restrict');
            $table->foreignId('beneficiary_id')->constrained()->onDelete('restrict');
            $table->enum('gateway', ['hub2', 'zeepay'])->index();
            $table->enum('payout_method', ['mobile_money', 'bank_transfer', 'cash_pickup']);
            $table->string('operator_code')->nullable();
            $table->decimal('payout_amount', 15, 2);
            $table->string('payout_currency', 3);
            $table->string('gateway_reference_id')->nullable()->unique()->comment('Hub2/Zeepay txn id');
            $table->string('merchant_reference_id')->unique();
            $table->enum('status', ['initiated','processing','submitting','completed','failed','refunded',])->default('initiated')->index();
            $table->string('gateway_status_code')->nullable()->comment('e.g., 200, PENDING_OTP, AUTH_FAILED');
            $table->text('gateway_status_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['gateway', 'status']);
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
