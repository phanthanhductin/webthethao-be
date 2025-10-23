<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Contact;

class ContactController extends Controller
{
    /** --------- PUBLIC: Khách gửi liên hệ --------- */
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:30',
            'email'   => 'nullable|email|max:255',
            'message' => 'nullable|string|max:5000',
            'title'   => 'nullable|string|max:1000',
        ]);

        try {
            $c = new Contact();

            // Map dữ liệu từ FE -> đúng cột của ptdt_contact
            $c->name     = $data['name'];
            $c->phone    = $data['phone'];
            $c->email    = $data['email'] ?? 'no-reply@sportoh.local';
            $c->title    = $data['title'] ?? ('Liên hệ từ '.$data['name']);
            $c->content  = $data['message'] ?? '';

            // Giá trị mặc định bắt buộc
            $c->status     = 0;                        // 0 = mới
            $c->reply_id   = 0;                        // nếu có hệ thống trả lời nội bộ
            $c->user_id    = auth()->id() ?? null;     // nullable
            $c->created_by = auth()->id() ?? 0;        // KHÔNG NULL
            $c->updated_by = auth()->id() ?? 0;        // KHÔNG NULL

            $c->save();

            return response()->json(['message' => 'OK', 'data' => $c], 201);
        } catch (\Throwable $e) {
            Log::error('Contact store failed', ['err' => $e->getMessage()]);
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /** --------- ADMIN: List + search + filter --------- */
    public function adminIndex(Request $r)
    {
        $q = Contact::query()->select([
            'id','name','email','phone',
            DB::raw('title   as subject'),
            DB::raw('content as message'),
            'reply_content','status','created_at','updated_at'
        ]);

        if (($kw = trim((string)$r->get('q',''))) !== '') {
            $safe = addcslashes($kw, "\\%_");
            $q->where(function($qq) use($safe){
                $qq->where('name', 'like', "%{$safe}%")
                   ->orWhere('email','like', "%{$safe}%")
                   ->orWhere('phone','like', "%{$safe}%")
                   ->orWhere('title','like', "%{$safe}%")
                   ->orWhere('content','like', "%{$safe}%");
            });
        }

        if ($r->filled('status')) {
            $q->where('status', (int)$r->status); // 0/1
        }

        $q->orderByDesc('created_at');
        $per  = min(max((int)$r->get('per_page', 20), 1), 200);
        $page = max((int)$r->get('page', 1), 1);

        $p = $q->paginate($per, ['*'], 'page', $page);

        return response()->json([
            'data'         => $p->items(),
            'current_page' => $p->currentPage(),
            'last_page'    => $p->lastPage(),
            'per_page'     => $p->perPage(),
            'total'        => $p->total(),
        ]);
    }

    /** --------- ADMIN: Detail --------- */
    public function adminShow($id)
    {
        $c = Contact::select([
            'id','name','email','phone',
            DB::raw('title   as subject'),
            DB::raw('content as message'),
            'reply_content','status','created_at','updated_at'
        ])->findOrFail((int)$id);

        // Nếu có cột read_at: $c->read_at = now(); $c->save();
        return response()->json($c);
    }

    /** --------- ADMIN: Cập nhật trạng thái/note (POST/PUT/PATCH đều vào đây) --------- */
    public function adminUpdate(Request $r, $id)
    {
        $c = Contact::findOrFail((int)$id);

        // Hỗ trợ cả FormData lẫn JSON
        $status = $r->has('status') ? $r->input('status') : null;
        $reply  = $r->has('reply_content') ? $r->input('reply_content') : $r->input('note'); // FE có thể gửi "note"

        $r->validate([
            'status'        => 'nullable|integer|in:0,1',
            'reply_content' => 'nullable|string|max:5000',
            'note'          => 'nullable|string|max:5000',
        ]);

        if ($status !== null) $c->status = (int)$status;
        if ($reply !== null)  $c->reply_content = (string)$reply;

        $c->updated_by = auth()->id() ?? 0;
        $c->save();

        return response()->json($c);
    }

    /** --------- ADMIN: Xoá --------- */
    public function destroy($id)
    {
        Contact::findOrFail((int)$id)->delete();
        return response()->json(['message' => 'deleted']);
    }

    /* ====== Ánh xạ cho apiResource (giữ nguyên resource hiện có) ====== */

    // GET /api/admin/contacts (apiResource -> index) → dùng adminIndex
    public function index(Request $r) { return $this->adminIndex($r); }

    // GET /api/admin/contacts/{id} (apiResource -> show) → dùng adminShow
    public function show($id) { return $this->adminShow($id); }

    // PUT/PATCH /api/admin/contacts/{id} (apiResource -> update) → dùng adminUpdate
    public function update(Request $r, $id) { return $this->adminUpdate($r, $id); }
}
