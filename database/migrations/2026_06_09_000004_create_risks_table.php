<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->unsignedTinyInteger('probability');
            $table->unsignedTinyInteger('impact');
            $table->string('proximity');
            $table->foreignId('risk_owner')->constrained('people');
            $table->string('response_type');
            $table->text('response_action')->nullable();
            $table->unsignedTinyInteger('residual_probability')->nullable();
            $table->unsignedTinyInteger('residual_impact')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('raised_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
