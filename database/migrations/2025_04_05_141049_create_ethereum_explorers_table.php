<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_explorers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title')->nullable();
            $table->string('base_url');
            $table->string('api_key')->nullable();
            $table->string('proxy')->nullable();
            $table->dateTime('sync_at')->nullable();
            $table->json('sync_data')->nullable();
            $table->bigInteger('requests')->default(0);
            $table->date('requests_at')->nullable();
            $table->boolean('worked')->default(true);
            $table->boolean('available')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_explorers');
    }
};
