<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_session_result_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_result_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_index')->nullable();
            $table->string('s3_key');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('mime_type');
            $table->foreignId('created_by')->constrained('people');
            $table->timestamp('created_at')->useCurrent();

            $table->index('test_session_result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_session_result_attachments');
    }
};
