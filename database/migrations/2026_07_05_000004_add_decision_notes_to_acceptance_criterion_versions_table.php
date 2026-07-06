<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acceptance_criterion_versions', function (Blueprint $table) {
            $table->text('supplier_decision_note')->nullable()->after('supplier_decision');
            $table->text('client_decision_note')->nullable()->after('client_decision');
        });
    }

    public function down(): void
    {
        Schema::table('acceptance_criterion_versions', function (Blueprint $table) {
            $table->dropColumn(['supplier_decision_note', 'client_decision_note']);
        });
    }
};
