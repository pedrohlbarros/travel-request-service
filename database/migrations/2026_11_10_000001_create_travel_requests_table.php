<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            table: 'travel_requests',
            callback: static function (Blueprint $table): void {
                $table->binary(column: 'id', length: 16, fixed: true)
                    ->primary();
                $table->binary(column: 'requester_id', length: 16, fixed: true);
                $table->string(column: 'destination', length: 120);
                $table->date(column: 'departure_date');
                $table->date(column: 'return_date');
                $table->string(column: 'status', length: 16);
                $table->unsignedInteger(column: 'version');
                $table->binary(column: 'decided_by', length: 16, fixed: true)
                    ->nullable();
                $table->timestamps();

                $table->index(columns: ['requester_id', 'id']);
                $table->index(columns: ['status', 'id']);
                $table->index(columns: ['created_at']);
                $table->index(columns: ['departure_date']);
                $table->index(columns: ['return_date']);

                $table->foreign(columns: 'requester_id')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
                $table->foreign(columns: 'decided_by')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'travel_requests');
    }
};
