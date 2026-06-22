<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_session_plan_scenarios', function (Blueprint $table) {
            $table->foreignId('test_session_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->primary(['test_session_plan_id', 'test_scenario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_session_plan_scenarios');
    }
};
