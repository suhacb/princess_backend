<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlight_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status')->default('draft');
            $table->string('budget_status')->nullable();
            $table->string('schedule_status')->nullable();
            $table->text('this_period_work');
            $table->text('next_period_work');
            $table->text('issues_summary')->nullable();
            $table->text('risks_summary')->nullable();
            $table->text('quality_summary')->nullable();
            $table->text('business_case_review')->nullable();
            $table->date('forecast_finish')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlight_reports');
    }
};
