<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_order_id', 50)->nullable();
            $table->string('link', 1000);
            $table->unsignedInteger('quantity');
            $table->decimal('rate', 18, 6);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('charge', 18, 6);
            $table->decimal('cost', 18, 6)->nullable();
            $table->unsignedInteger('start_count')->nullable();
            $table->unsignedInteger('remains')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('runs')->nullable();
            $table->unsignedInteger('interval')->nullable();
            $table->json('extra')->nullable();
            $table->string('source', 10)->default('web');
            $table->decimal('refunded_amount', 18, 6)->default(0);
            $table->timestamp('cancel_requested_at')->nullable();
            $table->text('provider_error')->nullable();
            $table->unsignedTinyInteger('send_attempts')->default(0);
            $table->text('admin_note')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'last_checked_at']);
            $table->index(['provider_id', 'provider_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
