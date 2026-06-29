<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('onlyoffice_key');
            $table->boolean('closed_without_changes')->default(false)->after('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['last_active_at', 'closed_without_changes']);
        });
    }
};
