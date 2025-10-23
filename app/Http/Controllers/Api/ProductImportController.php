<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;
use App\Exports\ProductsExport;

class ProductImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480', // tối đa ~20MB
        ]);

        try {
            Excel::import(new ProductsImport, $request->file('file'));
            return response()->json(['message' => 'Import thành công!']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Import thất bại',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function export()
    {
        $fileName = 'products_export_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new ProductsExport, $fileName);
    }
}