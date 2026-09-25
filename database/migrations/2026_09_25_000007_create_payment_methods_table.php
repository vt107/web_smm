<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('gateway', 30);
            $table->string('currency', 10);
            $table->decimal('exchange_rate', 18, 6)->default(1)->comment('Số đơn vị currency = 1 USD');
            $table->decimal('min_amount', 18, 6)->default(1);
            $table->decimal('max_amount', 18, 6)->default(10000);
            $table->decimal('bonus_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('deposit_ttl_minutes')->default(60);
            $table->text('instructions')->nullable();
            $table->text('config')->nullable();
            $table->string('scan_cursor', 100)->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
