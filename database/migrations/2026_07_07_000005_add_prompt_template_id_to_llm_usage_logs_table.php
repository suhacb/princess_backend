<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_usage_logs', function (Blueprint $table) {
            $table->foreignId('prompt_template_id')
                ->nullable()
                ->after('caller')
                ->constrained('prompt_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('llm_usage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prompt_template_id');
        });
    }
};
