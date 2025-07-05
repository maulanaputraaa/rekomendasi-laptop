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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('responder_name', 100);
            $table->foreignId('laptop_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating'); // 1–5
            $table->text('review')->nullable();
            $table->timestamps();

            $table->index('laptop_id');
            $table->index('responder_name');
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
