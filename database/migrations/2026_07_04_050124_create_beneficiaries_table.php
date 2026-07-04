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
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('country_code', 5);
            $table->string('city')->nullable();
            $table->string('transfer_type')->default('bank',)->comment('e.g., bank, mobile_wallet');
            $table->string('bank_or_wallet_name')->nullable();
            $table->string('account_or_wallet_number');
            $table->string('branch_name')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('swift_code')->nullable();
            $table->enum('status', ['pending', 'active', 'inactive', 'blocked'])->default('active');
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
