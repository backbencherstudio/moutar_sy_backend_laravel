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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->longText('site_name')->nullable();
            $table->string('site_logo', 191)->nullable();
            $table->string('admin_logo', 155)->nullable();
            $table->string('favicon', 191)->nullable();

            $table->string('seo_meta_description', 512)->nullable();
            $table->string('seo_keywords', 1024)->nullable();
            $table->string('seo_image', 191)->nullable();

            $table->enum('app_mode', ['local', 'live'])->nullable();

            $table->string('copyright_text', 124)->nullable();

            $table->string('facebook_url', 150)->nullable();
            $table->string('youtube_url', 150)->nullable();
            $table->string('twitter_url', 150)->nullable();
            $table->string('linkedin_url', 150)->nullable();
            $table->string('telegram_url', 150)->nullable();
            $table->string('instagram_url', 255)->nullable();

            $table->text('map_link')->nullable();

            $table->string('email', 150)->nullable();
            $table->string('whatsapp_number', 150)->nullable();
            $table->string('phone_no', 150)->nullable();
            $table->string('support_email', 30)->nullable();
            $table->string('address', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
