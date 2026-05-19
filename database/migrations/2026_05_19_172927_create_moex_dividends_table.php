<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moex_dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('symbol')->nullable();
            $table->string('ex_dividend_date')->nullable();
            $table->string('amount')->nullable();

            $table->string('source_hash', 40)->nullable();
            $table->timestamps();

            $table->unique('source_hash');
            $table->index('symbol');
            $table->index('company_id');
            $table->index('ex_dividend_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moex_dividends');
    }
};
