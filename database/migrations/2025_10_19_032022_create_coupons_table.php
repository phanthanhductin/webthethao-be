<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('ptdt_coupon', function (Blueprint $t) {
      $t->id();
      $t->string('code', 50)->unique();                 // MÃ: ABC10
      $t->enum('type', ['percent', 'fixed'])->default('percent');
      $t->decimal('value', 12, 2);                      // 10 (10%) hoặc 50000 (50k)
      $t->decimal('max_discount', 12, 2)->nullable();   // trần giảm (cho percent)
      $t->decimal('min_order_total', 12, 2)->default(0);// tối thiểu đơn
      $t->unsignedInteger('usage_limit')->nullable();   // tổng số lần dùng (null: vô hạn)
      $t->unsignedInteger('per_user_limit')->default(1);// mỗi user dùng tối đa
      $t->unsignedInteger('used_count')->default(0);
      $t->timestamp('start_at')->nullable();
      $t->timestamp('end_at')->nullable();
      $t->boolean('is_active')->default(true);
      $t->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('ptdt_coupon');
  }
};
