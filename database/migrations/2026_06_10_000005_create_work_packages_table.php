<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('team_manager_id')->constrained('people');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('techniques_and_processes')->nullable();
            $table->text('development_interfaces')->nullable();
            $table->text('operations_interfaces')->nullable();
            $table->text('configuration_management_requirements')->nullable();
            $table->text('constraints')->nullable();
            $table->text('reporting_requirements')->nullable();
            $table->string('tolerance_time')->nullable();
            $table->string('tolerance_cost')->nullable();
            $table->text('tolerance_scope')->nullable();
            $table->text('tolerance_quality')->nullable();
            $table->text('tolerance_risk')->nullable();
            $table->text('tolerance_benefits')->nullable();
            $table->date('planned_start');
            $table->date('planned_end');
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('authorized_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_packages');
    }
};
