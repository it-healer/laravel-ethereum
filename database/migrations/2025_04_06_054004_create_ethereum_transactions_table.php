<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItHealer\LaravelEthereum\Enums\TransactionType;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethereum_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txid')->index();
            $table->string('address')->index();
            $table->enum('type', array_column(TransactionType::cases(), 'value'));
            $table->timestamp('time_at');
            $table->string('from');
            $table->string('to');
            $table->decimal('amount', 30, 18);
            $table->string('token_address')->nullable();
            $table->bigInteger('block_number')->nullable();
            $table->json('data');

            $table->unique(['txid', 'address'], 'unique_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ethereum_transactions');
    }
};
