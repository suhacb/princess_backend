<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('parent_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('ref');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('role')->nullable();
            $table->string('action')->nullable();
            $table->text('benefit')->nullable();
            $table->string('priority');
            $table->string('status');
            $table->string('source')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('people')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('approved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('people');
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'ref']);
            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
