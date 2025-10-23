// database/migrations/2025_10_06_000001_add_published_at_to_ptdt_post.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('ptdt_post') && !Schema::hasColumn('ptdt_post','published_at')) {
      Schema::table('ptdt_post', function (Blueprint $t) {
        $t->timestamp('published_at')->nullable()->index()->after('status');
      });
    }
  }
  public function down(): void {
    if (Schema::hasTable('ptdt_post') && Schema::hasColumn('ptdt_post','published_at')) {
      Schema::table('ptdt_post', function (Blueprint $t) {
        $t->dropColumn('published_at');
      });
    }
  }
};
