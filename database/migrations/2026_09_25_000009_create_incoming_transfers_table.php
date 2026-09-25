<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained();
            $table->string('external_id', 150);
            $table->decimal('amount', 24, 8);
            $table->string('sender', 191)->nullable();
            $table->string('content', 500)->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->nullable();
            $table->timestamp('transferred_at');
            $table->string('status', 20)->default('unmatched');
            $table->foreignId('deposit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['payment_method_id', 'external_id']);
            $table->index(['status', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_transfers');
    }
};
