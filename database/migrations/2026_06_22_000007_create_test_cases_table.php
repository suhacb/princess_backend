<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->json('steps');
            $table->text('expected_result');
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
