<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptance_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->string('ref');
            $table->text('description');
            $table->text('measurement_method')->nullable();
            $table->string('acceptance_threshold')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('supplier_passed')->default(false);
            $table->timestamp('supplier_passed_at')->nullable();
            $table->boolean('client_passed')->default(false);
            $table->timestamp('client_passed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'ref']);
            $table->index(['project_id', 'status']);
            $table->index(['requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptance_criteria');
    }
};
