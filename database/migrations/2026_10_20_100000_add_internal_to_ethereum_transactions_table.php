<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Internal transfers (the ETH a contract sent while executing a transaction) share the hash of
 * their transaction with its other row: a swap is our outgoing call and the ETH it returned.
 * The flag joins the unique key so both rows can exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ethereum_transactions', function (Blueprint $table) {
            $table->boolean('internal')->default(false)->after('failed');
        });

        Schema::table('ethereum_transactions', function (Blueprint $table) {
            $table->unique(['txid', 'address', 'internal'], 'ethereum_transactions_unique_internal');
        });

        Schema::table('ethereum_transactions', function (Blueprint $table) {
            $table->dropUnique('unique_index');
        });
    }

    public function down(): void
    {
        // The old key cannot hold an internal row next to the other row of its transaction
        DB::table('ethereum_transactions')->where('internal', true)->delete();

        Schema::table('ethereum_transactions', function (Blueprint $table) {
            $table->unique(['txid', 'address'], 'unique_index');
        });

        Schema::table('ethereum_transactions', function (Blueprint $table) {
            $table->dropUnique('ethereum_transactions_unique_internal');
            $table->dropColumn('internal');
        });
    }
};
