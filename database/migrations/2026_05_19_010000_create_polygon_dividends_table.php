<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polygon_dividends', function (Blueprint $table) {
            $table->id();

            $table->string('polygon_id')->nullable();
            $table->string('ticker')->nullable();
            $table->string('cash_amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('declaration_date')->nullable();
            $table->string('dividend_type')->nullable();
            $table->string('ex_dividend_date')->nullable();
            $table->integer('frequency')->nullable();
            $table->string('pay_date')->nullable();
            $table->string('record_date')->nullable();

            $table->timestamps();

            $table->unique('polygon_id');
            $table->index('ticker');
            $table->index('declaration_date');
            $table->index('ex_dividend_date');
            $table->index('pay_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polygon_dividends');
    }
};

