<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('preconditions')->nullable();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->boolean('is_testable')->default(false);
            $table->text('testable_notes')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'ref']);
            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'is_testable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_scenarios');
    }
};
