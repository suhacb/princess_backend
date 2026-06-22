<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_document_requirements', function (Blueprint $table) {
            $table->foreignId('qa_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();

            $table->primary(['qa_document_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_document_requirements');
    }
};
