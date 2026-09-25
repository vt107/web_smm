<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name', 50);
            $table->string('symbol', 5);
            $table->string('symbol_position', 6)->default('before');
            $table->decimal('rate', 18, 6)->comment('Số đơn vị tiền này = 1 USD');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('auto_update')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
