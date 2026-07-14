<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name');
            $table->string('event_type');
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->longText('payload');
            $table->enum('status', ['unprocessed', 'processed', 'failed'])->default('unprocessed');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
