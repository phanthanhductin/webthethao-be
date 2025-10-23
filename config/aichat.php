<?php
// return [
//     'shop_name'   => 'THETHAO SPORTS',
//     'shop_open'   => '08:00',
//     'shop_close'  => '21:00',

//     'product_price_col'      => 'price_root',
//     'product_sale_price_col' => 'price_sale',
//     'order_status_done'      => [4],

//     'frontend_origin'        => '',
//     'frontend_product_path'  => '/san-pham/{slug}',
//     'frontend_category_path' => '/danh-muc/{slug}',
//     'asset_origin'           => '',

//     // ✅ BẬT CHẾ ĐỘ “HỎI GÌ TRẢ LỜI ĐÓ”
//     'strict_mode'            => true,
// ];


// config/aichat.php
return [
    // Chế độ "ngắn gọn"
    'strict_mode' => true,

    // Thông tin shop + giờ làm việc
    'shop_name'   => env('AICHAT_SHOP_NAME', 'THETHAO SPORTS'),
    'shop_open'   => env('AICHAT_OPEN', '08:00'),   // hoặc null
    'shop_close'  => env('AICHAT_CLOSE','21:30'),   // hoặc null

    // Cột giá trong ptdt_product
    // nếu DB bạn khác tên cột, chỉnh tại đây (VD: price_root, price_sale, price, sale_price…)
    'product_price_col'       => env('AICHAT_PRICE_COL', 'price_root'),
    'product_sale_price_col'  => env('AICHAT_SALE_COL',  'price_sale'),

    // URL sinh link sản phẩm/danh mục/ảnh
    'frontend_origin'         => env('AICHAT_FRONTEND_ORIGIN', ''),           // VD: https://sieuthamini.vercel.app
    'frontend_product_path'   => env('AICHAT_PRODUCT_PATH',  '/products/{slug}'),
    'frontend_category_path'  => env('AICHAT_CATEGORY_PATH', '/products?category={slug}'),
    'asset_origin'            => env('AICHAT_ASSET_ORIGIN', ''),               // VD: http://127.0.0.1:8000

    // Trạng thái "đơn đã hoàn tất" trong ptdt_order.status (số)
    'order_status_done'       => explode(',', env('AICHAT_ORDER_DONE', '4')),
];
