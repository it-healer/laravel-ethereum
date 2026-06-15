<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_alchemy_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique();
            $table->text('signing_key');
            $table->unsignedInteger('addresses_count')->default(0);
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_alchemy_webhooks');
    }
};
