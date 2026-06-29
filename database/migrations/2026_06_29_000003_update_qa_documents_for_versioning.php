<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa_documents', function (Blueprint $table) {
            // Must be nullable — existing rows have no versions yet.
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('id')
                ->constrained('document_versions')
                ->nullOnDelete();

            // file_name and file_reference were nulled out in the #104 migration;
            // drop the columns now that versioning carries this data.
            $table->dropColumn(['file_name', 'file_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('qa_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
            $table->string('file_name')->nullable();
            $table->string('file_reference')->nullable();
        });
    }
};
