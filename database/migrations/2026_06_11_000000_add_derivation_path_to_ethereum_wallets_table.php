<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ethereum_wallets', function (Blueprint $table) {
            $table->string('derivation_path')->default("m/44'/60'/0'/0/{index}")->after('seed');
        });
    }

    public function down(): void
    {
        Schema::table('ethereum_wallets', function (Blueprint $table) {
            $table->dropColumn('derivation_path');
        });
    }
};
