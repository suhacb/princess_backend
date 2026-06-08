<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->unsignedInteger('sequence')->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->string('tolerance_time')->nullable();
            $table->string('tolerance_cost')->nullable();
            $table->string('tolerance_scope')->nullable();
            $table->string('tolerance_risk')->nullable();
            $table->string('tolerance_quality')->nullable();
            $table->string('tolerance_benefit')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
