<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('identifier')->nullable();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->text('composition')->nullable();
            $table->text('derivation')->nullable();
            $table->text('format_and_presentation')->nullable();
            $table->string('type');
            $table->jsonb('quality_criteria')->nullable();
            $table->text('quality_tolerance')->nullable();
            $table->text('quality_method')->nullable();
            $table->text('quality_skills_required')->nullable();
            $table->jsonb('quality_responsibilities')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('baselined_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
