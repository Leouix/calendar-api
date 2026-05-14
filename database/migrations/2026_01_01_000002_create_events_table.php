<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('title');
            $table->date('event_date');
            $table->json('payload_json')->nullable();
            $table->string('source', 50);
            $table->timestamps();

            $table->index('event_type');
            $table->index('event_date');
            $table->index(['company_id', 'event_type', 'event_date', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
