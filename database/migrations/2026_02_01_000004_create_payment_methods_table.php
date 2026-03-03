<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['checking', 'savings', 'cash', 'investment', 'credit_card', 'prepaid_card']);
            $table->string('currency', 3)->default('CLP');
            $table->bigInteger('credit_limit')->nullable();
            $table->unsignedTinyInteger('billing_cycle_day')->nullable();
            $table->unsignedTinyInteger('payment_due_day')->nullable();
            $table->string('color', 7)->default('#10B981');
            $table->string('icon', 50)->nullable();
            $table->string('last_four_digits', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
