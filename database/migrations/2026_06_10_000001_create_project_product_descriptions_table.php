<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_product_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->text('composition')->nullable();
            $table->text('derivation')->nullable();
            $table->text('format_and_presentation')->nullable();
            $table->jsonb('quality_criteria')->nullable();
            $table->text('quality_tolerance')->nullable();
            $table->text('quality_method')->nullable();
            $table->text('quality_skills_required')->nullable();
            $table->jsonb('quality_responsibilities')->nullable();
            $table->text('customer_quality_expectations')->nullable();
            $table->jsonb('acceptance_criteria')->nullable();
            $table->text('acceptance_methods')->nullable();
            $table->text('acceptance_responsibilities')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('baselined_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_product_descriptions');
    }
};
