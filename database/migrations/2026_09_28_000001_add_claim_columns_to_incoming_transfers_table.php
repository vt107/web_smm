<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Khách xin nhận giao dịch crypto chưa khớp → chờ admin duyệt (tx hash công khai nên không cho tự cộng tiền). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_transfers', function (Blueprint $table) {
            $table->foreignId('claimed_by')->nullable()->after('deposit_id')->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropColumn('claimed_at');
        });
    }
};
