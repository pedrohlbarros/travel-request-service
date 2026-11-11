<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            table: 'outbox_messages',
            callback: static function (Blueprint $table): void {
                $table->binary(column: 'id', length: 16, fixed: true)
                    ->primary();
                $table->string(column: 'exchange', length: 120);
                $table->string(column: 'routing_key', length: 120);
                $table->longText(column: 'payload');
                $table->json(column: 'headers');
                $table->dateTime(column: 'occurred_at');
                $table->dateTime(column: 'published_at')
                    ->nullable();
                $table->index(columns: ['published_at', 'id']);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'outbox_messages');
    }
};
