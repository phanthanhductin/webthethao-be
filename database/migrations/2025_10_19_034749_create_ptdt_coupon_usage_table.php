<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('ptdt_coupon_usage', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('coupon_id')->index();
      $t->unsignedBigInteger('user_id')->nullable()->index();
      $t->unsignedBigInteger('order_id')->nullable()->index();
      $t->string('code', 50)->index();
      $t->decimal('discount_amount', 12, 2)->default(0);
      $t->timestamps();

      $t->foreign('coupon_id')->references('id')->on('ptdt_coupon')->onDelete('cascade');
    });
  }
  public function down(): void {
    Schema::dropIfExists('ptdt_coupon_usage');
  }
};
