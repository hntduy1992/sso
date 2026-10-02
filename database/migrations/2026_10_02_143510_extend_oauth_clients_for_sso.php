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
        Schema::table('oauth_clients', function (Blueprint $table) {
            // PUBLIC = SPA/Mobile (no secret, PKCE required)
            // CONFIDENTIAL = Backend app (has secret)
            $table->enum('client_type', ['PUBLIC', 'CONFIDENTIAL'])->default('CONFIDENTIAL')->after('name');

            // Force PKCE for PUBLIC clients — cannot be disabled for public clients
            $table->boolean('pkce_enforced')->default(true)->after('client_type');

            // Trusted first-party apps skip the Consent screen
            $table->boolean('is_trusted')->default(false)->after('pkce_enforced');

            // Used for OIDC Backchannel Logout — IdP posts logout_token here
            $table->string('backchannel_logout_uri')->nullable()->after('is_trusted');

            // Human-readable description shown on consent screen
            $table->string('description')->nullable()->after('backchannel_logout_uri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn([
                'client_type',
                'pkce_enforced',
                'is_trusted',
                'backchannel_logout_uri',
                'description',
            ]);
        });
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
