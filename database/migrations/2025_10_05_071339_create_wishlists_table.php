<?php

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration {
//     public function up(): void {
//         Schema::create('wishlists', function (Blueprint $table) {
//             $table->id();
//             $table->unsignedBigInteger('user_id')->index();
//             $table->unsignedBigInteger('product_id')->index();
//             $table->timestamps();

//             $table->unique(['user_id', 'product_id']);
//             // Không đặt foreign key để tránh xung đột với bảng ptdt_product (sqlite càng dễ lỗi FK).
//         });
//     }
//     public function down(): void {
//         Schema::dropIfExists('wishlists');
//     }
// };

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // ✅ Chỉ tạo nếu chưa tồn tại
        if (!Schema::hasTable('wishlists')) {
            Schema::create('wishlists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->timestamps();

                $table->unique(['user_id', 'product_id']);
                // Không đặt foreign key để tránh xung đột với bảng ptdt_product (sqlite càng dễ lỗi FK)
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('wishlists');
    }
};
