<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_register_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('quality_method');
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->json('reviewers')->nullable();
            $table->string('result')->nullable();
            $table->text('issues_raised')->nullable();
            $table->foreignId('sign_off_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('sign_off_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_register_entries');
    }
};
