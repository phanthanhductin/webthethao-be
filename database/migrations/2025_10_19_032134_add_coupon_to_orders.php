<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('ptdt_order', function (Blueprint $t) {
      if (!Schema::hasColumn('ptdt_order', 'coupon_code')) {
        $t->string('coupon_code', 50)->nullable()->after('note');
      }
      if (!Schema::hasColumn('ptdt_order', 'discount_amount')) {
        $t->decimal('discount_amount', 12, 2)->default(0)->after('coupon_code');
      }
      if (!Schema::hasColumn('ptdt_order', 'subtotal')) {
        $t->decimal('subtotal', 12, 2)->default(0)->after('discount_amount');
      }
      if (!Schema::hasColumn('ptdt_order', 'total')) {
        $t->decimal('total', 12, 2)->default(0)->after('subtotal');
      }
    });
  }

  public function down(): void {
    Schema::table('ptdt_order', function (Blueprint $t) {
      foreach (['coupon_code','discount_amount','subtotal','total'] as $c) {
        if (Schema::hasColumn('ptdt_order', $c)) $t->dropColumn($c);
      }
    });
  }
};
