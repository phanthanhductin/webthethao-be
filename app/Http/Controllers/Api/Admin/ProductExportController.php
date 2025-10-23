<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsExport;

class ProductExportController extends Controller
{
    public function export(Request $request)
    {
        // Nhận params lọc (giống filter bên list)
        $filters = [
            'q'           => $request->query('q'),
            'category_id' => $request->query('category_id'),
            'status'      => $request->query('status'),
            'only_sale'   => $request->boolean('only_sale'),
            'in_stock'    => $request->boolean('in_stock'),
        ];

        // Định dạng file: xlsx | csv (mặc định xlsx)
        $format   = $request->query('format', 'xlsx');
        $filename = 'products_export_' . now()->format('Ymd_His') . '.' . $format;

        $export = new ProductsExport($filters);

        // Trả file về cho trình duyệt tải
        return Excel::download($export, $filename, $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }
}
