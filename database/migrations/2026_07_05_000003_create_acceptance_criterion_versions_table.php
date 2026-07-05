<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptance_criterion_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acceptance_criterion_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('description');
            $table->foreignId('verifier_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('verification_method')->nullable();
            $table->string('status');
            $table->boolean('supplier_passed');
            $table->boolean('client_passed');
            $table->string('supplier_decision');
            $table->string('client_decision');
            $table->foreignId('created_by')->constrained('people');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['acceptance_criterion_id', 'version_number']);
            $table->index('acceptance_criterion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptance_criterion_versions');
    }
};
