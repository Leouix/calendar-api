<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('ticker', 20);
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_import_logs');
    }
};
