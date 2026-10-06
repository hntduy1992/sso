<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Represents organizational units: both specialized teams (Tổ chuyên môn)
     * and the management board (Ban Giám đốc) as a distinct non-team entity.
     *
     * type = 'specialized_team' → Tổ chuyên môn (has Tổ trưởng, Tổ phó, Tổ viên)
     * type = 'management_board' → Ban Giám đốc (has Giám đốc, Phó Giám đốc)
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->enum('type', ['specialized_team', 'management_board']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
