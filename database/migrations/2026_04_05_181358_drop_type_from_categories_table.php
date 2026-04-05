<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_user_id_type_index');
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type')->after('name')->default('expense');
            $table->index(['user_id', 'type']);
        });
    }
};
