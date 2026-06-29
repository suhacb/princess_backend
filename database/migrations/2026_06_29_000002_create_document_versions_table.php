<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('qa_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('s3_key');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->uuid('onlyoffice_key')->nullable()->unique();
            $table->string('converted_md_key')->nullable();
            $table->string('comment')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'version_number']);
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
