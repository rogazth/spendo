<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['expense', 'income', 'transfer_out', 'transfer_in', 'settlement']);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('instrument_id')->nullable();
            $table->unsignedBigInteger('from_instrument_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('linked_transaction_id')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('instrument_amount')->nullable();
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->string('currency', 3)->default('CLP');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('exclude_from_budget')->default(false);
            $table->timestamp('transaction_date');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->foreign('instrument_id')
                ->references('id')
                ->on('instruments')
                ->nullOnDelete();

            $table->foreign('from_instrument_id')
                ->references('id')
                ->on('instruments')
                ->nullOnDelete();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'type', 'exclude_from_budget', 'transaction_date']);
            $table->index(['account_id', 'transaction_date']);
            $table->index('account_id');
            $table->index('instrument_id');
            $table->index('from_instrument_id');
            $table->index('category_id');
            $table->index('linked_transaction_id');
        });

        // Add self-referencing FK after table creation
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('linked_transaction_id')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
