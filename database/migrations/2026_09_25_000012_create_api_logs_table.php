<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20)->nullable();
            $table->string('ip', 45);
            $table->json('request')->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->string('error')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
