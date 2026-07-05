<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('priority');
            $table->string('status');
            $table->string('role')->nullable();
            $table->string('action')->nullable();
            $table->text('benefit')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('created_by')->constrained('people');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['requirement_id', 'version_number']);
            $table->index('requirement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_versions');
    }
};
