<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProductsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function collection()
    {
        return Product::with(['brand:id,name', 'category:id,name'])
            ->select([
                'id',
                'name',
                'slug',
                'brand_id',
                'category_id',
                'price_root',
                'price_sale',
                'qty',
                'status',
                'thumbnail',
                'description',
                'detail',
            ])
            ->get()
            ->map(function ($p) {
                return [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'slug'        => $p->slug,
                    'brand_id'    => $p->brand_id,
                    'category_id' => $p->category_id,
                    'brand_name'  => $p->brand->name ?? '',
                    'category_name' => $p->category->name ?? '',
                    'price_root'  => $p->price_root,
                    'price_sale'  => $p->price_sale,
                    'qty'         => $p->qty,
                    'status'      => $p->status,
                    'thumbnail'   => $p->thumbnail, // đường dẫn tương đối
                    'thumbnail_url' => $p->thumbnail ? url('/storage/'.$p->thumbnail) : '',
                    'description' => $p->description,
                    'detail'      => $p->detail,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tên sản phẩm',
            'Slug',
            'Brand ID',
            'Category ID',
            'Brand Name',
            'Category Name',
            'Giá gốc',
            'Giá sale',
            'Tồn kho',
            'Trạng thái',
            'Thumbnail',
            'Thumbnail URL',
            'Mô tả',
            'Chi tiết',
        ];
    }
}