<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_country');
            $table->string('from_country_flag');
            $table->string('from_currency', 3);
            $table->string('to_country');
            $table->string('to_country_flag');
            $table->string('to_currency', 3);
            $table->decimal('customer_rate', 15, 6);
            $table->decimal('fixed_fee', 8, 2)->default(0.00);
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
