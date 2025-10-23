<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            // Đổi quantity -> qty_change nếu còn tồn tại
            if (Schema::hasColumn('stock_movements', 'quantity') &&
                !Schema::hasColumn('stock_movements', 'qty_change')) {
                $t->renameColumn('quantity', 'qty_change');
            }

            if (!Schema::hasColumn('stock_movements', 'qty_change')) {
                $t->integer('qty_change');
            }
            if (!Schema::hasColumn('stock_movements', 'type')) {
                $t->string('type', 20);
            }
            if (!Schema::hasColumn('stock_movements', 'note')) {
                $t->string('note', 255)->nullable();
            }
            if (!Schema::hasColumn('stock_movements', 'ref_type')) {
                $t->string('ref_type', 20)->default('manual');
            }
            if (!Schema::hasColumn('stock_movements', 'ref_id')) {
                $t->unsignedBigInteger('ref_id')->nullable();
            }
            if (!Schema::hasColumn('stock_movements', 'created_by')) {
                $t->unsignedBigInteger('created_by')->nullable();
            }

            // đảm bảo có timestamps
            if (!Schema::hasColumn('stock_movements', 'created_at')) {
                $t->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('stock_movements', 'updated_at')) {
                $t->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            // Không rollback rename để tránh mất dữ liệu
            // Chỉ xóa các cột phụ thêm
            if (Schema::hasColumn('stock_movements', 'ref_type')) $t->dropColumn('ref_type');
            if (Schema::hasColumn('stock_movements', 'ref_id')) $t->dropColumn('ref_id');
            if (Schema::hasColumn('stock_movements', 'created_by')) $t->dropColumn('created_by');
        });
    }
};
