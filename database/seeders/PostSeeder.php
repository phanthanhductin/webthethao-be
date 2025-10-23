<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $rows = [
            [
                'title' => 'Khai trương cửa hàng thể thao mới',
                'slug' => Str::slug('Khai trương cửa hàng thể thao mới'),
                'image_url' => 'https://placehold.co/1200x600?text=News+1',
                'summary' => 'Nhiều ưu đãi hấp dẫn trong tuần lễ khai trương.',
                'content' => '<p>Chúng tôi vừa khai trương cửa hàng mới với nhiều dòng sản phẩm thể thao...</p>',
                'status' => 1,
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ],
            [
                'title' => 'Bí quyết chọn giày chạy bộ',
                'slug' => Str::slug('Bí quyết chọn giày chạy bộ'),
                'image_url' => 'https://placehold.co/1200x600?text=News+2',
                'summary' => 'Những tiêu chí quan trọng khi chọn giày chạy phù hợp.',
                'content' => '<p>Để chọn giày chạy bộ phù hợp, bạn cần lưu ý form chân, mặt sân, cự ly...</p>',
                'status' => 1,
                'created_at' => $now->copy()->subDays(1),
                'updated_at' => $now->copy()->subDays(1),
            ],
            [
                'title' => 'Top 5 phụ kiện tập gym nên có',
                'slug' => Str::slug('Top 5 phụ kiện tập gym nên có'),
                'image_url' => 'https://placehold.co/1200x600?text=News+3',
                'summary' => 'Danh sách 5 phụ kiện hữu ích cho người mới tập.',
                'content' => '<p>Dây kháng lực, găng tay, bình nước có vạch đo, khăn thể thao, dây nhảy...</p>',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $r) {
            // upsert theo slug để chạy nhiều lần không trùng
            DB::table('ptdt_post')->updateOrInsert(['slug' => $r['slug']], $r);
        }
    }
}
