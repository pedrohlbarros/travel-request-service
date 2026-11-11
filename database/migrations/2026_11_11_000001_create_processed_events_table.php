<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            table: 'processed_events',
            callback: static function (Blueprint $table): void {
                $table->string(column: 'consumer', length: 120);
                $table->binary(column: 'event_id', length: 16, fixed: true);
                $table->dateTime(column: 'processed_at');

                $table->primary(columns: ['consumer', 'event_id']);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'processed_events');
    }
};
