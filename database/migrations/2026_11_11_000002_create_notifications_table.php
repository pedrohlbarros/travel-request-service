<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            table: 'notifications',
            callback: static function (Blueprint $table): void {
                $table->binary(column: 'id', length: 16, fixed: true)
                    ->primary();
                $table->binary(column: 'notifiable_id', length: 16, fixed: true);
                $table->string(column: 'type', length: 120);
                $table->text(column: 'message');
                $table->text(column: 'data');
                $table->dateTime(column: 'read_at')
                    ->nullable();
                $table->dateTime(column: 'created_at');

                $table->index(columns: ['notifiable_id', 'id']);
                $table->index(columns: ['notifiable_id', 'read_at', 'id']);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'notifications');
    }
};
