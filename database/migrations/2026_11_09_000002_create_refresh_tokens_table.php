<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            table: 'refresh_tokens',
            callback: static function (Blueprint $table): void {
                $table->binary(column: 'id', length: 16, fixed: true)
                    ->primary();
                $table->binary(column: 'family_id', length: 16, fixed: true)
                    ->index();
                $table->binary(column: 'user_id', length: 16, fixed: true);
                $table->char(column: 'token_hash', length: 64)
                    ->unique();
                $table->timestamp(column: 'issued_at');
                $table->timestamp(column: 'expires_at');
                $table->timestamp(column: 'rotated_at')
                    ->nullable();
                $table->timestamp(column: 'revoked_at')
                    ->nullable();

                $table->foreign(columns: 'user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'refresh_tokens');
    }
};
