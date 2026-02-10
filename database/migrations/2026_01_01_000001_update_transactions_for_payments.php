<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'reference')) {
                $table->string('reference')->unique()->after('id');
            }
            if (!Schema::hasColumn('transactions', 'external_ref')) {
                $table->string('external_ref')->nullable()->after('reference');
            }
            if (!Schema::hasColumn('transactions', 'initiator_user_id')) {
                $table->unsignedBigInteger('initiator_user_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('transactions', 'meta')) {
                $table->json('meta')->nullable()->after('description');
            }
            if (!Schema::hasColumn('transactions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('meta');
            }

            // Normalize to string enums (payment/payout) + default pending
            $table->string('type')->default('payment')->change();
            $table->string('status')->default('pending')->change();

            $table->index(['cr_wallet_id', 'dr_wallet_id']);
            $table->index('reference');
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'initiator_user_id')) {
                $table->foreign('initiator_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'initiator_user_id')) {
                $table->dropForeign(['initiator_user_id']);
                $table->dropColumn('initiator_user_id');
            }
            if (Schema::hasColumn('transactions', 'external_ref')) {
                $table->dropColumn('external_ref');
            }
            if (Schema::hasColumn('transactions', 'reference')) {
                $table->dropUnique('transactions_reference_unique');
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('transactions', 'meta')) {
                $table->dropColumn('meta');
            }
            if (Schema::hasColumn('transactions', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            $table->dropIndex(['cr_wallet_id', 'dr_wallet_id']);
            $table->dropIndex(['reference']);
        });
    }
};
