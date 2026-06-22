<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_package_products', function (Blueprint $table) {
            $table->foreignId('work_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['work_package_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_package_products');
    }
};
