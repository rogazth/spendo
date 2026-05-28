<?php

use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type')->default(TransactionType::Regular->value)->after('linked_transaction_id');
        });

        DB::table('transactions')
            ->whereNotNull('linked_transaction_id')
            ->update([
                'type' => TransactionType::Transfer->value,
                'category_id' => null,
            ]);

        $initialBalanceCategoryIds = DB::table('categories')
            ->where('name', 'Balance Inicial')
            ->where('is_system', true)
            ->pluck('id');

        if ($initialBalanceCategoryIds->isNotEmpty()) {
            DB::table('transactions')
                ->whereIn('category_id', $initialBalanceCategoryIds)
                ->update([
                    'type' => TransactionType::InitialBalance->value,
                    'category_id' => null,
                ]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type', 'transaction_date']);
            $table->dropColumn('type');
        });
    }
};
