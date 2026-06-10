<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->string('request_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('raised_by')->constrained('people');
            $table->timestamp('raised_at')->useCurrent();
            $table->text('impact_assessment')->nullable();
            $table->string('priority')->nullable();
            $table->string('status')->default('proposed');
            $table->foreignId('decision_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->text('decision_rationale')->nullable();
            $table->date('implementation_due')->nullable();
            $table->date('implemented_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changes');
    }
};
