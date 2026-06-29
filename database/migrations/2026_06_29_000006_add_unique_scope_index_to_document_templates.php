<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // NULLs are distinct in PostgreSQL unique indexes, so we use COALESCE
        // to give each nullable column a sentinel value before combining them.
        // project_id: -1 sentinel (real IDs start at 1)
        // category / type: '' sentinel
        // Partial index excludes soft-deleted rows so a deleted template does
        // not block re-creation of the same scope.
        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX document_templates_scope_unique
            ON document_templates (
                COALESCE(project_id, -1),
                COALESCE(category, ''),
                COALESCE(type, '')
            )
            WHERE deleted_at IS NULL;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP INDEX IF EXISTS document_templates_scope_unique;');
    }
};
