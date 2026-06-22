<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkpoint_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_package_id')->nullable()->constrained('work_packages')->nullOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status')->default('draft');
            $table->text('achievements');
            $table->text('planned_next_period');
            $table->text('issues_this_period')->nullable();
            $table->text('issues_forecast')->nullable();
            $table->text('quality_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'work_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoint_reports');
    }
};
