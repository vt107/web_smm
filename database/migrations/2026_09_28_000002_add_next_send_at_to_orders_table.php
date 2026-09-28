<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Thời điểm cron orders:send được gửi (lại) đơn chưa gửi được sang provider. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('next_send_at')->nullable()->after('send_attempts');
            $table->index(['status', 'next_send_at']);
        });

        // Đơn đang kẹt (chưa gửi, chưa huỷ) → để cron gửi lại ngay.
        DB::table('orders')
            ->where('status', 'pending')
            ->whereNull('provider_order_id')
            ->whereNotNull('provider_id')
            ->whereNull('cancel_requested_at')
            ->update(['next_send_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_send_at']);
            $table->dropColumn('next_send_at');
        });
    }
};
