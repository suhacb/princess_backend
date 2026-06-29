<?php

use App\Enums\QaDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa_documents', function (Blueprint $table) {
            // Temporary default so existing rows get a value; booted() keeps it in sync going forward.
            $table->string('category')->after('type')->default('qa');
            $table->nullableMorphs('documentable');
            $table->json('metadata')->nullable()->after('description');
        });

        // Backfill category from existing type values.
        foreach (QaDocumentType::cases() as $type) {
            DB::table('qa_documents')
                ->where('type', $type->value)
                ->update(['category' => $type->category()->value]);
        }

        // Deprecate file_name and file_reference — columns stay until DOC-10 removes them.
        DB::table('qa_documents')->update(['file_name' => null, 'file_reference' => null]);

        Schema::table('qa_documents', function (Blueprint $table) {
            $table->index(['project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('qa_documents', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'category']);
            $table->dropMorphs('documentable');
            $table->dropColumn(['category', 'metadata']);
        });
    }
};
