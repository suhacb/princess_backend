<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_session_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->date('session_date');
            $table->foreignId('tester_id')->constrained('people');
            $table->string('team_type');
            $table->string('environment')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'ref']);
            $table->index(['project_id', 'team_type']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'tester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_sessions');
    }
};
