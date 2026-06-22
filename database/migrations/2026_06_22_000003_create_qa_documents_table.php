<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->string('file_name')->nullable();
            // Nullable string reference to a SharePoint URL or future Phase 3 document_id
            $table->string('file_reference')->nullable();
            $table->string('status');
            $table->foreignId('supersedes_id')->nullable()->constrained('qa_documents')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_documents');
    }
};
