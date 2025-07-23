<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Models\EthereumNode;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(EthereumNode::class, 'node_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignIdFor(EthereumExplorer::class, 'explorer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('name')->unique();
            $table->string('title')->nullable();
            $table->text('password')->nullable();
            $table->text('mnemonic')->nullable();
            $table->text('seed')->nullable();
            $table->dateTime('sync_at')->nullable();
            $table->decimal('balance', 30, 18)->nullable();
            $table->json('tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_wallets');
    }
};
