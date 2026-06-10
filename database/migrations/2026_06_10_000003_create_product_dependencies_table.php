<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('predecessor_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('successor_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['predecessor_id', 'successor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_dependencies');
    }
};
