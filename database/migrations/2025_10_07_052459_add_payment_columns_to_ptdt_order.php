<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ptdt_order', function (Blueprint $table) {
            if (!Schema::hasColumn('ptdt_order', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('note');
            }
            if (!Schema::hasColumn('ptdt_order', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('payment_method');
            }
            if (!Schema::hasColumn('ptdt_order', 'payment_ref')) {
                $table->string('payment_ref')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('ptdt_order', 'payment_amount')) {
                $table->unsignedBigInteger('payment_amount')->default(0)->after('payment_ref');
            }
            if (!Schema::hasColumn('ptdt_order', 'payment_at')) {
                $table->timestamp('payment_at')->nullable()->after('payment_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ptdt_order', function (Blueprint $table) {
            if (Schema::hasColumn('ptdt_order', 'payment_at')) $table->dropColumn('payment_at');
            if (Schema::hasColumn('ptdt_order', 'payment_amount')) $table->dropColumn('payment_amount');
            if (Schema::hasColumn('ptdt_order', 'payment_ref')) $table->dropColumn('payment_ref');
            if (Schema::hasColumn('ptdt_order', 'payment_status')) $table->dropColumn('payment_status');
            if (Schema::hasColumn('ptdt_order', 'payment_method')) $table->dropColumn('payment_method');
        });
    }
};
