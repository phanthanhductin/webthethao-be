<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ptdt_product', function (Blueprint $t) {
            if (!Schema::hasColumn('ptdt_product','qty'))    $t->integer('qty')->default(0)->after('price_sale');
            if (!Schema::hasColumn('ptdt_product','status')) $t->string('status')->nullable()->after('qty');
        });
    }
    public function down(): void {
        Schema::table('ptdt_product', function (Blueprint $t) {
            if (Schema::hasColumn('ptdt_product','qty')) $t->dropColumn('qty');
            if (Schema::hasColumn('ptdt_product','status')) $t->dropColumn('status');
        });
    }
};
