<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshots of a budget's intent (caps, scope, dates) at the moment a cycle
     * closed. Spending is never stored here: it stays derived from transactions
     * so editing or backdating a transaction still corrects a closed cycle.
     */
    public function up(): void
    {
        Schema::create('budget_cycles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3);
            $table->string('budget_name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamp('closed_at');
            $table->boolean('is_backfilled')->default(false);
            $table->timestamps();

            $table->unique(['budget_id', 'starts_at']);
            $table->index(['user_id', 'ends_at']);
        });

        Schema::create('budget_cycle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name');
            $table->string('category_color', 20)->nullable();
            $table->string('category_emoji', 16)->nullable();
            $table->json('category_ids');
            $table->integer('cap_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_cycle_items');
        Schema::dropIfExists('budget_cycles');
    }
};
