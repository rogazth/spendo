<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['credit_card', 'debit_card', 'prepaid_card', 'cash', 'transfer']);
            $table->unsignedBigInteger('linked_account_id')->nullable();
            $table->string('currency', 3)->default('CLP');
            $table->bigInteger('credit_limit')->nullable();
            $table->unsignedTinyInteger('billing_cycle_day')->nullable();
            $table->unsignedTinyInteger('payment_due_day')->nullable();
            $table->string('color', 7)->default('#10B981');
            $table->string('icon', 50)->nullable();
            $table->string('last_four_digits', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('linked_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'type']);
            $table->index('linked_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
