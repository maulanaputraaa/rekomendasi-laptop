<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('laptop_prices');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('laptop_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laptop_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 15, 2);
            $table->string('source')->default('import');
            $table->timestamps();
        });
    }
};
