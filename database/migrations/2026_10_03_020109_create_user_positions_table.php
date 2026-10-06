<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The core HRM assignment table. Models organizational positions including
     * concurrent/dual-role assignments (kiêm nhiệm).
     *
     * Concurrent role example (BR-02):
     *   A Phó Giám đốc (is_primary=true in management_board) can also hold
     *   Tổ trưởng roles (is_primary=false) in one or more specialized teams.
     *
     * BR-03: A user may only have ONE active row where is_primary=true.
     * BR-04: Each specialized team may only have ONE active TEAM_LEAD.
     * BR-05: ended_at IS NULL means the position is currently active.
     */
    public function up(): void
    {
        Schema::create('user_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_type_id')->constrained()->cascadeOnDelete();

            // Date the user started in this position
            $table->date('started_at');

            // NULL = currently active; set when the position is terminated or transferred
            $table->date('ended_at')->nullable();

            // TRUE = primary/main position; FALSE = concurrent/secondary (kiêm nhiệm)
            // Business rule: only ONE active row per user may have is_primary=true
            $table->boolean('is_primary')->default(true);

            // Optional notes (e.g. reason for transfer, appointment decision number)
            $table->text('notes')->nullable();

            $table->timestamps();

            // Optimisation indexes
            $table->index(['user_id', 'ended_at']);
            $table->index(['department_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_positions');
    }
};
