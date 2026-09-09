<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // beginning 
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('country_name');
            $table->string('mobile_name');
            $table->string('mobile_number');
            $table->string('beneficiary_name');
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
