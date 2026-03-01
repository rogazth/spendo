<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('CLP');
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'bimonthly']);
            $table->date('anchor_date');
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->index('user_id');
            $table->index('account_id');
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'anchor_date', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
