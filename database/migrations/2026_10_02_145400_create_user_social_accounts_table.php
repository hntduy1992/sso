<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores linked social provider accounts for each user.
     * Allows one user to have multiple social logins (Google, GitHub, etc.)
     * and supports account linking when email already exists.
     */
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // e.g. 'google', 'github', 'microsoft'
            $table->string('provider');

            // The unique ID returned by the social provider
            $table->string('provider_id');

            // Store the provider access token for API calls (optional)
            $table->text('provider_token')->nullable();
            $table->text('provider_refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamps();

            // One provider account per user
            $table->unique(['user_id', 'provider']);
            // A provider ID can only be linked to one SSO account
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
