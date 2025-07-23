<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumToken;
use ItHealer\LaravelEthereum\Models\EthereumWallet;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(EthereumWallet::class, 'wallet_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignIdFor(EthereumAddress::class, 'address_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignIdFor(EthereumToken::class, 'token_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('txid');
            $table->decimal('amount', 30, 18);
            $table->bigInteger('block_number')->nullable();
            $table->integer('confirmations')->default(0);
            $table->timestamp('time_at');

            $table->unique(['address_id', 'txid'], 'unique_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_deposits');
    }
};
