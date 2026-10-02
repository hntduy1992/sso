<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks refresh token families for Refresh Token Rotation + Revocation Family.
     * When a revoked refresh token is re-used, the entire family is revoked to
     * neutralize token theft scenarios.
     */
    public function up(): void
    {
        Schema::create('oauth_refresh_token_families', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The root access token that started this family chain
            $table->char('root_access_token_id', 80)->index();

            // FK to oauth_refresh_tokens (Passport manages this table)
            $table->char('current_refresh_token_id', 100)->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id');

            // Revocation state — set true when theft detected (reuse of rotated token)
            $table->boolean('revoked')->default(false)->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();

            $table->timestamps();
            $table->timestamp('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_token_families');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
