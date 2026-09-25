<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dữ liệu của provider giả lập (App\Sandbox) – chỉ dùng để test local. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_orders', function (Blueprint $table) {
            $table->id();
            $table->string('service_id', 20);
            $table->string('link', 1000);
            $table->unsignedInteger('quantity');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sandbox_refills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sandbox_order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_refills');
        Schema::dropIfExists('sandbox_orders');
    }
};
