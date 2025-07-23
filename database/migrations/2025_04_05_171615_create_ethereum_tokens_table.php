<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->string('name');
            $table->string('symbol');
            $table->integer('decimals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_tokens');
    }
};
