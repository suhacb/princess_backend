<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acceptance_criteria', function (Blueprint $table) {
            $table->string('title')->nullable()->after('ref');
            $table->unsignedInteger('version')->default(1)->after('status');

            $table->foreignId('verifier_id')->nullable()->after('acceptance_threshold')
                ->constrained('people')->nullOnDelete();
            $table->string('verification_method')->nullable()->after('verifier_id');

            $table->string('supplier_decision')->default('pending')->after('supplier_passed_at');
            $table->foreignId('supplier_decided_by')->nullable()->after('supplier_decision')
                ->constrained('people')->nullOnDelete();
            $table->timestamp('supplier_decided_at')->nullable()->after('supplier_decided_by');
            $table->text('supplier_decision_note')->nullable()->after('supplier_decided_at');

            $table->string('client_decision')->default('pending')->after('client_passed_at');
            $table->foreignId('client_decided_by')->nullable()->after('client_decision')
                ->constrained('people')->nullOnDelete();
            $table->timestamp('client_decided_at')->nullable()->after('client_decided_by');
            $table->text('client_decision_note')->nullable()->after('client_decided_at');
        });

        // Backfill title for any pre-existing rows, then enforce NOT NULL.
        DB::table('acceptance_criteria')->whereNull('title')->update([
            'title' => DB::raw('left(description, 255)'),
        ]);

        DB::statement('ALTER TABLE acceptance_criteria ALTER COLUMN title SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('acceptance_criteria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verifier_id');
            $table->dropConstrainedForeignId('supplier_decided_by');
            $table->dropConstrainedForeignId('client_decided_by');

            $table->dropColumn([
                'title',
                'version',
                'verification_method',
                'supplier_decision',
                'supplier_decided_at',
                'supplier_decision_note',
                'client_decision',
                'client_decided_at',
                'client_decision_note',
            ]);
        });
    }
};
