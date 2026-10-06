<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lookup table for position types (chức danh).
     * The `applicable_to` column restricts which department type a position
     * can be assigned to, preventing logical errors (e.g. assigning
     * "Giám đốc" to a specialized_team).
     *
     * Seeded values:
     *   MEMBER      → Tổ viên          (specialized_team only)
     *   DEPUTY_TL   → Tổ phó           (specialized_team only)
     *   TEAM_LEAD   → Tổ trưởng        (specialized_team only)
     *   DEPUTY_DIR  → Phó Giám đốc     (management_board only)
     *   DIRECTOR    → Giám đốc         (management_board only)
     */
    public function up(): void
    {
        Schema::create('position_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);

            // Hierarchy level: higher = more authority (used for display ordering & rules)
            $table->unsignedTinyInteger('level');

            // Restricts which department type may use this position
            $table->enum('applicable_to', ['specialized_team', 'management_board', 'both'])
                ->default('both');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_types');
    }
};
