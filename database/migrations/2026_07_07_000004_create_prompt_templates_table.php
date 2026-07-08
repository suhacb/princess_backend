<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->text('body');
            $table->foreignId('created_by')->constrained('people');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
