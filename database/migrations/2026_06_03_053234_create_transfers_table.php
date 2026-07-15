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
        $table->foreignId('user_id')->constrained()->onDelete('restrict'); // Sender
        $table->foreignId('beneficiary_id')->constrained()->onDelete('restrict');
        $table->string('payout_method')->default('stripe');
        $table->string('operator_code')->nullable();
        $table->decimal('payout_amount', 15, 2);
        $table->string('payout_currency', 3);
        $table->string('gateway_reference_id')->nullable()->unique()->comment('stripe transfer/payout ID');
        $table->enum('status', ['initiated', 'processing', 'submitting', 'completed', 'failed', 'refunded'])->default('initiated')->index();
        $table->timestamp('processed_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
