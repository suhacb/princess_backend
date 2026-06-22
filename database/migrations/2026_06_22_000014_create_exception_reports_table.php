<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exception_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->string('trigger_type');
            $table->text('description');
            $table->text('cause');
            $table->text('impact');
            $table->json('options')->nullable();
            $table->text('recommendation');
            $table->string('status')->default('draft');
            $table->text('board_decision')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'stage_id']);
            $table->index(['project_id', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_reports');
    }
};
