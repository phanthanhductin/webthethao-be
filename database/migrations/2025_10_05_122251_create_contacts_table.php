<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('ptdt_contact')) {
      Schema::table('ptdt_contact', function (Blueprint $t) {
        if (Schema::hasColumn('ptdt_contact','created_by')) $t->unsignedInteger('created_by')->default(0)->change();
        if (Schema::hasColumn('ptdt_contact','updated_by')) $t->unsignedInteger('updated_by')->default(0)->change();
      });
    }
  }
  public function down(): void {
    if (Schema::hasTable('ptdt_contact')) {
      Schema::table('ptdt_contact', function (Blueprint $t) {
        if (Schema::hasColumn('ptdt_contact','created_by')) $t->unsignedInteger('created_by')->nullable(false)->default(null)->change();
        if (Schema::hasColumn('ptdt_contact','updated_by')) $t->unsignedInteger('updated_by')->nullable(false)->default(null)->change();
      });
    }
  }
};
