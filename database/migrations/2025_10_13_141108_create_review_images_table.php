<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->string('path'); // "reviews/abc.webp"
            $table->timestamps();

            $table->foreign('review_id')
                ->references('id')->on('product_reviews')
                ->onDelete('cascade');

            $table->index('review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_images');
    }
};
