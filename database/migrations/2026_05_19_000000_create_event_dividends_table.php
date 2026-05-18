<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('symbol')->nullable();
            $table->string('ex_dividend_date')->nullable();
            $table->string('declaration_date')->nullable();
            $table->string('record_date')->nullable();
            $table->string('payment_date')->nullable();
            $table->string('amount')->nullable();

            $table->string('source', 50)->nullable();
            $table->string('source_hash', 40)->nullable();
            $table->timestamps();

            $table->index('source_hash');
            $table->index('symbol');
            $table->index(['company_id', 'source']);
            $table->index('declaration_date');
            $table->index('ex_dividend_date');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_dividends');
    }
};

