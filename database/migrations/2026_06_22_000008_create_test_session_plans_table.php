<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_session_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('planned_date');
            $table->string('team_type');
            $table->foreignId('assignee_id')->nullable()->constrained('people');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'ref']);
            $table->index(['project_id', 'team_type']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'planned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_session_plans');
    }
};
