<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Explicitly rebuild the table to:
        // 1) rename from generic "events" to earnings-only storage
        // 2) drop the legacy `event_type` column
        //
        // Data loss is acceptable per project requirements.
        Schema::dropIfExists('event_earnings');
        Schema::dropIfExists('events');

        Schema::create('event_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('event_date');
            $table->json('payload_json')->nullable();
            $table->string('source', 50);
            $table->string('source_hash', 40)->nullable();
            $table->timestamps();

            $table->index('event_date');
            $table->index('source_hash');
            $table->index(['company_id', 'event_date', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_earnings');

        // Restore the legacy table to make rollbacks possible.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('title');
            $table->date('event_date');
            $table->json('payload_json')->nullable();
            $table->string('source', 50);
            $table->string('source_hash', 40)->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('event_date');
            $table->index('source_hash');
            $table->index(['company_id', 'event_type', 'event_date', 'source']);
        });
    }
};

