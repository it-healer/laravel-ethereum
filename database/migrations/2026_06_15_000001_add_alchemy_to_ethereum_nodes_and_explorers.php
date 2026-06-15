<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ethereum_explorers', function (Blueprint $table) {
            if (!Schema::hasColumn('ethereum_explorers', 'driver')) {
                $table->string('driver')->default('etherscan_v2')->after('title');
            }
            if (!Schema::hasColumn('ethereum_explorers', 'credits')) {
                $table->unsignedBigInteger('credits')->default(0)->after('requests_at');
                $table->dateTime('credits_at')->nullable()->after('credits');
            }
        });

        Schema::table('ethereum_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('ethereum_nodes', 'credits')) {
                $table->unsignedBigInteger('credits')->default(0)->after('requests_at');
                $table->dateTime('credits_at')->nullable()->after('credits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ethereum_explorers', function (Blueprint $table) {
            $table->dropColumn(['driver', 'credits', 'credits_at']);
        });

        Schema::table('ethereum_nodes', function (Blueprint $table) {
            $table->dropColumn(['credits', 'credits_at']);
        });
    }
};
