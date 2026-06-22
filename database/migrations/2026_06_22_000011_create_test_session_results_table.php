<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_session_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('result')->default('not_run');
            $table->text('notes')->nullable();
            $table->string('defect_ref')->nullable();
            $table->timestamp('executed_at')->nullable();

            $table->unique(['test_session_id', 'test_scenario_id']);
            $table->index(['test_scenario_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_session_results');
    }
};
