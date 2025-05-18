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
        Schema::create('laptops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->string('series');
            $table->string('model');
            $table->string('cpu');
            $table->string('ram');
            $table->string('storage');
            $table->string('gpu');
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->index('brand_id');
            $table->index('price');
            $table->index('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laptops');
    }
};
