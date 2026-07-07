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
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->unique();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();

            $table->string('country')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->enum('document_type', ['passport', 'id_card', 'driving_license', 'nid'])->nullable();
            $table->string('document_number')->nullable();
            $table->string('nid_number')->nullable();
            $table->string('passport_number')->nullable();
            $table->date('document_expiry_date')->nullable();

            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'review',
            ])->default('pending');

            $table->text('rejection_reason')->nullable();

            $table->string('didit_session_id')->nullable()->unique();
            $table->string('didit_verification_id')->nullable();
            $table->string('didit_workflow_id')->nullable();
            $table->string('didit_attempt_id')->nullable();

            $table->json('didit_response')->nullable();
            $table->json('didit_webhook_payload')->nullable();
            $table->json('didit_verification_data')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('didit_webhook_received_at')->nullable();

            $table->integer('attempt_count')->default(0);
            $table->timestamps();
            $table->unique('didit_session_id');
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('didit_verification_id');
            $table->index('verified_at');
            $table->index('document_type');
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
