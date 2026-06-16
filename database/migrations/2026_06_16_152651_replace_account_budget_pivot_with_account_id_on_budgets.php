<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A budget now belongs to a single account, while an account may back many
     * budgets. Collapse the many-to-many pivot into a nullable account_id FK and
     * backfill from the existing pivot rows (one account per budget today).
     */
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });

        if (Schema::hasTable('account_budget')) {
            DB::table('account_budget')
                ->orderBy('id')
                ->get(['account_id', 'budget_id'])
                ->groupBy('budget_id')
                ->each(function ($rows, $budgetId): void {
                    DB::table('budgets')
                        ->where('id', $budgetId)
                        ->update(['account_id' => $rows->first()->account_id]);
                });

            Schema::dropIfExists('account_budget');
        }
    }

    public function down(): void
    {
        Schema::create('account_budget', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'budget_id']);
        });

        DB::table('budgets')
            ->whereNotNull('account_id')
            ->get(['id', 'account_id'])
            ->each(function ($budget): void {
                DB::table('account_budget')->insert([
                    'account_id' => $budget->account_id,
                    'budget_id' => $budget->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
