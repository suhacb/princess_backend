<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_session_results', function (Blueprint $table) {
            $table->dropUnique(['test_session_id', 'test_scenario_id']);

            $table->foreignId('test_case_id')->nullable()->after('test_scenario_id')
                ->constrained()->cascadeOnDelete();
            $table->json('step_results')->nullable()->after('result');

            $table->unique(['test_session_id', 'test_scenario_id', 'test_case_id']);
        });
    }

    public function down(): void
    {
        Schema::table('test_session_results', function (Blueprint $table) {
            $table->dropUnique(['test_session_id', 'test_scenario_id', 'test_case_id']);
            $table->dropConstrainedForeignId('test_case_id');
            $table->dropColumn('step_results');

            $table->unique(['test_session_id', 'test_scenario_id']);
        });
    }
};
