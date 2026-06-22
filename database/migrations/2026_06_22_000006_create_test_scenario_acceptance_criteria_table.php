<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_scenario_acceptance_criteria', function (Blueprint $table) {
            $table->foreignId('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acceptance_criterion_id')->constrained()->cascadeOnDelete();
            $table->primary(['test_scenario_id', 'acceptance_criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_scenario_acceptance_criteria');
    }
};
