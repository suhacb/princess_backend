<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_activity_log_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Audit log entries are immutable and cannot be modified or deleted';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER activity_log_no_update
                BEFORE UPDATE ON activity_log
                FOR EACH ROW EXECUTE FUNCTION prevent_activity_log_modification();

            CREATE TRIGGER activity_log_no_delete
                BEFORE DELETE ON activity_log
                FOR EACH ROW EXECUTE FUNCTION prevent_activity_log_modification();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS activity_log_no_update ON activity_log;
            DROP TRIGGER IF EXISTS activity_log_no_delete ON activity_log;
            DROP FUNCTION IF EXISTS prevent_activity_log_modification();
        SQL);
    }
};
