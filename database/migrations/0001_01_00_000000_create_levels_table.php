<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->decimal('min_spent', 18, 6)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('auto_upgrade')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort')->default(0);
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
