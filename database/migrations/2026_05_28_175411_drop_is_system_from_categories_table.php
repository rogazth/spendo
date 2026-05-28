<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $systemNames = [
        'Balance Inicial',
        'Ajuste de Balance',
        'Transferencia',
        'Liquidación TDC',
    ];

    public function up(): void
    {
        $systemCategoryIds = DB::table('categories')
            ->where('is_system', true)
            ->whereIn('name', $this->systemNames)
            ->pluck('id');

        if ($systemCategoryIds->isNotEmpty()) {
            DB::table('transactions')
                ->whereIn('category_id', $systemCategoryIds)
                ->update(['category_id' => null]);

            DB::table('budget_items')
                ->whereIn('category_id', $systemCategoryIds)
                ->delete();

            DB::table('categories')
                ->whereIn('id', $systemCategoryIds)
                ->delete();
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_system')->default(false);
        });
    }
};
