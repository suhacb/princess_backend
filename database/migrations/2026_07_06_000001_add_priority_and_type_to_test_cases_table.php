<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('expected_result');
            $table->string('type')->default('positive')->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            $table->dropColumn(['priority', 'type']);
        });
    }
};
