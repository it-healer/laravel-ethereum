<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItHealer\LaravelEthereum\Models\EthereumWallet;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(EthereumWallet::class, 'wallet_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('address');
            $table->string('title')->nullable();
            $table->boolean('watch_only')->nullable();
            $table->text('private_key')->nullable();
            $table->integer('index')->nullable();
            $table->decimal('balance', 30, 18)->nullable();
            $table->json('tokens')->nullable();
            $table->timestamp('touch_at')->nullable();
            $table->timestamp('sync_at')->nullable();
            $table->bigInteger('sync_block_number')->nullable();
            $table->boolean('available')->default(true);
            $table->timestamps();

            $table->unique(['wallet_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_addresses');
    }
};
