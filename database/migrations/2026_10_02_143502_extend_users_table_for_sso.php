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
        Schema::table('users', function (Blueprint $table) {
            // Make password nullable to support social-only accounts
            $table->string('password')->nullable()->change();

            // Profile fields
            $table->string('avatar_url')->nullable()->after('email');

            // SSO security tracking
            $table->timestamp('password_changed_at')->nullable()->after('password');

            // MFA / TOTP fields
            $table->boolean('two_factor_enabled')->default(false)->after('password_changed_at');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
            $table->text('two_factor_secret')->nullable()->after('two_factor_confirmed_at');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn([
                'avatar_url',
                'password_changed_at',
                'two_factor_enabled',
                'two_factor_confirmed_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
            ]);
        });
    }
};
