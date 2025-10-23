<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema; // <-- thêm dòng này

class BrandController extends Controller
{
    // GET /api/brands
    public function index(Request $r)
    {
        $q = Brand::query();

        if ($r->get('status') === 'active') {
            $q->where('status', 1);
        }
        if ($kw = trim((string)$r->get('q',''))) {
            $q->where(function($x) use ($kw) {
                $x->where('name','like',"%$kw%")
                  ->orWhere('slug','like',"%$kw%");
            });
        }

        // ✅ Chỉ select cột đang có
        $cols = ['id','name','slug','status'];
        if (Schema::hasColumn('ptdt_brand','logo')) {
            $cols[] = 'logo';
        }

        return response()->json($q->orderBy('name')->get($cols));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:ptdt_brand,slug',
            'status' => 'nullable|integer|in:0,1',
        ]);
        $b = new Brand();
        $b->name = $data['name'];
        $b->slug = $data['slug'] ?: Str::slug($data['name']);
        $b->status = $data['status'] ?? 1;
        $b->save();
        return response()->json($b, 201);
    }
}
