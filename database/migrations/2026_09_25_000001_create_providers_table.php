<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('api_url');
            $table->text('api_key');
            $table->string('driver', 30)->default('perfectpanel');
            $table->decimal('balance', 18, 6)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
