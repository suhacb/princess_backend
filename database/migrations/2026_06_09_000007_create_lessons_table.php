<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->nullable();
            $table->text('description');
            $table->text('recommendation')->nullable();
            $table->foreignId('raised_by')->constrained('people');
            $table->timestamp('raised_at')->useCurrent();
            $table->string('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
