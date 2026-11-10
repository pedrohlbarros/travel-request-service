<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            table: 'users',
            callback: static function (Blueprint $table): void {
                $table->binary(column: 'id', length: 16, fixed: true)
                    ->primary();
                $table->string(column: 'name', length: 120);
                $table->string(column: 'email', length: 254)
                    ->unique();
                $table->string(column: 'password');
                $table->string(column: 'role', length: 16);
                $table->timestamps();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'users');
    }
};
