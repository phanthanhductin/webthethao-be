// database/migrations/2025_01_02_000001_add_logo_to_ptdt_brand.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('ptdt_brand') && !Schema::hasColumn('ptdt_brand','logo')) {
      Schema::table('ptdt_brand', function (Blueprint $t) {
        $t->string('logo', 512)->nullable()->after('slug');
      });
    }
  }
  public function down(): void {
    if (Schema::hasTable('ptdt_brand') && Schema::hasColumn('ptdt_brand','logo')) {
      Schema::table('ptdt_brand', function (Blueprint $t) {
        $t->dropColumn('logo');
      });
    }
  }
};
