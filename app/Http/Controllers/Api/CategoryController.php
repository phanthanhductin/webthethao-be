<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /* ============================================================
     |  PUBLIC: Danh sách & Chi tiết
     * ============================================================ */

    public function index()
    {
        $cats = Category::orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json($cats);
    }

    public function show($id)
    {
        $cat = Category::find($id);
        if (!$cat) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        return response()->json($cat);
    }

    /* ============================================================
     |  ADMIN: CRUD chính
     * ============================================================ */

    // Thêm mới
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:1000',
            'slug'        => 'required|string|max:1000|unique:ptdt_category,slug',
            'image'       => 'nullable|string|max:1000',
            'parent_id'   => 'nullable|integer',
            'sort_order'  => 'nullable|integer',
            'description' => 'nullable|string',
            'status'      => 'nullable|integer|in:0,1',
        ]);

        $data['parent_id']  = $data['parent_id'] ?? null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status']     = $data['status'] ?? 1;
        $data['created_by'] = auth()->id() ?? 0;
        $data['updated_by'] = auth()->id() ?? 0;

        $cat = Category::create($data);

        return response()->json([
            'message'  => 'Thêm danh mục thành công',
            'category' => $cat,
        ], 201);
    }

    // Cập nhật
    public function update(Request $request, $id)
    {
        $cat = Category::find($id);
        if (!$cat) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $data = $request->validate([
            'name'        => 'required|string|max:1000',
            'slug'        => 'required|string|max:1000|unique:ptdt_category,slug,' . $id,
            'image'       => 'nullable|string|max:1000',
            'parent_id'   => 'nullable|integer',
            'sort_order'  => 'nullable|integer',
            'description' => 'nullable|string',
            'status'      => 'nullable|integer|in:0,1',
        ]);

        $data['parent_id']  = $data['parent_id'] ?? null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status']     = $data['status'] ?? 1;
        $data['updated_by'] = auth()->id() ?? 0;

        $cat->update($data);

        return response()->json([
            'message'  => 'Cập nhật danh mục thành công',
            'category' => $cat,
        ]);
    }

    // ✅ Xóa mềm (chuyển vào thùng rác)
    public function destroy($id)
    {
        $cat = Category::find($id);
        if (!$cat) {
            return response()->json(['message' => 'Không tìm thấy danh mục'], 404);
        }

        $cat->delete();
        return response()->json(['message' => 'Đã chuyển vào thùng rác.']);
    }

    /* ============================================================
     |  ADMIN: Thùng rác (Soft Delete)
     * ============================================================ */

    // Danh sách trong thùng rác
    public function trash()
    {
        $list = Category::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json(['data' => $list]);
    }

    // Khôi phục từ thùng rác
    public function restore($id)
    {
        $cat = Category::onlyTrashed()->find($id);
        if (!$cat) {
            return response()->json(['message' => 'Không tìm thấy danh mục'], 404);
        }

        $cat->restore();
        return response()->json(['message' => 'Đã khôi phục danh mục.']);
    }

    // Xóa vĩnh viễn
    public function forceDelete($id)
    {
        $cat = Category::onlyTrashed()->find($id);
        if (!$cat) {
            return response()->json(['message' => 'Không tìm thấy danh mục'], 404);
        }

        $cat->forceDelete();
        return response()->json(['message' => 'Đã xóa vĩnh viễn danh mục.']);
    }
}
