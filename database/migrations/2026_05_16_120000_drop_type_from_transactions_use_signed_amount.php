<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE transactions
            SET amount = -amount
            WHERE type IN ('expense', 'transfer_out')
                AND amount > 0
        SQL);

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type']);
            $table->dropIndex(['user_id', 'type', 'exclude_from_budget', 'transaction_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'exclude_from_budget', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'exclude_from_budget', 'transaction_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['expense', 'income', 'transfer_out', 'transfer_in'])->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE transactions
            SET type = CASE
                WHEN linked_transaction_id IS NULL AND amount < 0 THEN 'expense'
                WHEN linked_transaction_id IS NULL AND amount >= 0 THEN 'income'
                WHEN linked_transaction_id IS NOT NULL AND amount < 0 THEN 'transfer_out'
                ELSE 'transfer_in'
            END
        SQL);

        DB::statement(<<<'SQL'
            UPDATE transactions
            SET amount = ABS(amount)
        SQL);

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'type', 'exclude_from_budget', 'transaction_date']);
        });
    }
};
