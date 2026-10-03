<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores personal and HR profile information for each SSO account.
     * Intentionally separated from the `users` table (Account) to enforce
     * the architectural boundary between identity/auth and personal data.
     *
     * Relationship: users (Account) 1:1 user_profiles (Profile)
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Personal information
            $table->string('full_name', 255)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('phone_number', 20)->nullable();

            // Contact info (separate from SSO login email)
            $table->string('contact_email', 255)->nullable();
            $table->string('address', 500)->nullable();

            // Avatar uploaded by user (distinct from avatar_url in users for social login avatars)
            $table->string('avatar_path', 500)->nullable();

            // Short biography / additional notes
            $table->text('bio')->nullable();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
