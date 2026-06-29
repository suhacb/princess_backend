<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->string('s3_key')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->foreignId('created_by')->constrained('people');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
