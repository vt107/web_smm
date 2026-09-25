<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('payment_method_id')->constrained();
            $table->string('code', 30)->unique();
            $table->decimal('requested_amount', 18, 6)->comment('USD');
            $table->decimal('paid_amount', 18, 6)->comment('Số tiền cần chuyển theo paid_currency');
            $table->string('paid_currency', 10);
            $table->decimal('exchange_rate', 18, 6);
            $table->string('pay_to', 191);
            $table->decimal('received_amount', 18, 6)->nullable();
            $table->decimal('amount', 18, 6)->nullable()->comment('USD cộng vào số dư');
            $table->decimal('bonus', 18, 6)->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('matched_by', 10)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['payment_method_id', 'status', 'paid_amount']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
