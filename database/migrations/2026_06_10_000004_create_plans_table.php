<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('replaces_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->date('planned_start');
            $table->date('planned_end');
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->string('tolerance_time')->nullable();
            $table->string('tolerance_cost')->nullable();
            $table->text('tolerance_scope')->nullable();
            $table->text('tolerance_quality')->nullable();
            $table->text('tolerance_risk')->nullable();
            $table->text('tolerance_benefits')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('external_dependencies')->nullable();
            $table->text('monitoring_and_reporting')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
