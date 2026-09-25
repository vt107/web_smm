<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('fulfillment', 10)->default('api');
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_service_id', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 40)->default('default');
            $table->decimal('rate', 18, 6)->comment('Giá bán / 1000 (package: giá / gói)');
            $table->decimal('provider_rate', 18, 6)->nullable();
            $table->unsignedInteger('min');
            $table->unsignedInteger('max');
            $table->boolean('refill')->default(false);
            $table->unsignedSmallInteger('refill_days')->nullable();
            $table->boolean('cancel')->default(false);
            $table->boolean('dripfeed')->default(false);
            $table->unsignedInteger('average_time')->nullable()->comment('Phút');
            $table->integer('sort')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status', 'sort']);
            $table->index(['provider_id', 'provider_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
