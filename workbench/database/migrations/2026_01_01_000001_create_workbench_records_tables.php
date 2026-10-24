<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->timestamps();
        });
    }
};
