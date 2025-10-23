<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('sku')->nullable()->unique();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->decimal('price_root', 12, 0)->nullable();
            $table->decimal('price_sale', 12, 0)->nullable();
            $table->integer('qty')->default(0);
            $table->json('images')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('ptdt_product')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('product_variants'); }
};
