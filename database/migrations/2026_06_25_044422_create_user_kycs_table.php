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
        Schema::create('user_kycs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('country');
            $table->enum('document_type', ['passport', 'id_card']);
            $table->string('front_image');
            $table->string('back_image')->nullable();
            // front_image
            $table->string('id_card_number')->nullable()->unique();
            $table->string('name_on_card')->nullable();
            $table->string('father_name_on_card')->nullable();

            // back_image
            $table->text('address_on_card')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_kycs');
    }
};
