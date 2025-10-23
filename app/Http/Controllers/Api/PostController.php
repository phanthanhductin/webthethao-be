<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth; // <-- THÊM

class PostController extends Controller
{
    /** Chuẩn hoá đường dẫn ảnh thành URL tuyệt đối */
    private static function toAbsolute(?string $path): ?string
    {
        if (!$path) return null;
        $s = trim($path);

        if (preg_match('#^data:image/[^;]+;base64,#i', $s)) return $s;
        if (preg_match('#^https?://#i', $s)) return $s;
        if (str_starts_with($s, '//')) {
            $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
            return $scheme . ':' . $s;
        }

        $s = ltrim(str_replace('\\', '/', $s), '/');

        if (Str::startsWith($s, 'public/')) $s = substr($s, 7);
        if (Str::startsWith($s, 'storage/')) return url($s);

        if (preg_match('#^(assets|images|img|uploads|media|files)(/|$)#i', $s)) {
            if (File::exists(public_path($s))) return url($s);
            if (File::exists(storage_path('app/public/'.$s))) return url('storage/'.$s);
            return url($s);
        }

        $candidates = [
            $s, 'assets/images/'.$s, 'assets/img/'.$s, 'assets/'.$s,
            'images/'.$s, 'img/'.$s, 'uploads/'.$s, 'media/'.$s, 'files/'.$s,
        ];
        foreach ($candidates as $rel) {
            if (File::exists(public_path($rel))) return url($rel);
        }
        if (File::exists(storage_path('app/public/'.$s))) return url('storage/'.$s);

        return url('assets/images/'.$s);
    }

    /** ===================== PUBLIC APIs ===================== */

    // GET /api/posts?q=&category_id=&status=&page=&per_page=
    public function index(Request $r)
    {
        $table = (new Post)->getTable();

        $selectable = array_values(array_filter([
            'id','title','slug','created_at',
            'summary','description','detail','content',
            'thumbnail','image','thumb','banner','cover','image_url',
            'status','category_id',
        ], fn ($c) => Schema::hasColumn($table, $c)));

        $q = Post::query()->select($selectable);

        if ($r->filled('status') && Schema::hasColumn($table, 'status')) {
            $q->where('status', (int) $r->get('status'));
        }
        if ($r->filled('category_id') && Schema::hasColumn($table, 'category_id')) {
            $q->where('category_id', (int) $r->get('category_id'));
        }

        if (($kw = trim((string) $r->get('q', ''))) !== '') {
            $safe = addcslashes($kw, "\\%_");
            $searchCols = array_values(array_filter([
                'title','slug','summary','description','detail','content'
            ], fn ($c) => Schema::hasColumn($table, $c)));

            if (!empty($searchCols)) {
                $q->where(function ($qq) use ($searchCols, $safe) {
                    foreach ($searchCols as $i => $col) {
                        $i === 0
                            ? $qq->where($col, 'like', "%{$safe}%")
                            : $qq->orWhere($col, 'like', "%{$safe}%");
                    }
                });
            }
        }

        $orderCol = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id';
        $q->orderByDesc($orderCol);

        $perPage = min(max((int) $r->get('per_page', 12), 1), 100);
        $page    = max((int) $r->get('page', 1), 1);

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);
        $paginator->getCollection()->transform(function (Post $p) {
            $candidate = $p->image_url ?? $p->thumbnail ?? $p->image ?? $p->thumb ?? $p->banner ?? $p->cover ?? null;
            return [
                'id'         => $p->id,
                'title'      => (string)($p->title ?? ''),
                'slug'       => $p->slug ?: Str::slug(($p->title ?? 'post').'-'.$p->id),
                'image_url'  => self::toAbsolute($candidate),
                'summary'    => $p->summary ?? $p->description ?? null,
                'created_at' => optional($p->created_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'data'          => $paginator->items(),
            'current_page'  => $paginator->currentPage(),
            'last_page'     => $paginator->lastPage(),
            'per_page'      => $paginator->perPage(),
            'total'         => $paginator->total(),
        ]);
    }

    // GET /api/posts/{idOrSlug}
    public function show($idOrSlug)
    {
        $table = (new Post)->getTable();

        if (ctype_digit((string) $idOrSlug)) {
            $post = Post::find((int) $idOrSlug);
        } else {
            $post = Schema::hasColumn($table, 'slug')
                ? Post::where('slug', $idOrSlug)->first()
                : Post::find((int) $idOrSlug);
        }

        if (!$post) return response()->json(['message' => 'Post not found'], 404);

        $candidate = $post->image_url ?? $post->thumbnail ?? $post->image ?? $post->thumb ?? $post->banner ?? $post->cover ?? null;

        return response()->json([
            'id'         => $post->id,
            'title'      => (string)($post->title ?? ''),
            'slug'       => $post->slug ?: Str::slug(($post->title ?? 'post').'-'.$post->id),
            'image_url'  => self::toAbsolute($candidate),
            'summary'    => $post->summary ?? $post->description ?? null,
            'content'    => $post->content ?? $post->detail ?? null,
            'status'     => $post->status ?? null,
            'category_id'=> $post->category_id ?? null,
            'created_at' => optional($post->created_at)->toDateTimeString(),
            'updated_at' => optional($post->updated_at)->toDateTimeString(),
        ]);
    }

    /** ===================== ADMIN APIs ===================== */

    // GET /api/admin/posts
    public function adminIndex(Request $r)
    {
        return $this->index($r);
    }

    // GET /api/admin/posts/{id}
    public function adminShow($id)
    {
        $post = Post::findOrFail((int)$id);
        return response()->json($post);
    }

    // POST /api/admin/posts
    public function store(Request $r)
    {
        $table = (new Post)->getTable();

        $data = $r->validate([
            'title'       => ['required','string','max:255'],
            'slug'        => ['nullable','string','max:255', Rule::unique($table,'slug')],
            'summary'     => ['nullable','string','max:512'],
            'content'     => ['nullable','string'],
            'status'      => ['nullable','integer','in:0,1'],
            'category_id' => ['nullable','integer'],
            'published_at'=> ['nullable','date'],
            'image'       => ['nullable','image','max:2048'],
            'image_url'   => ['nullable','string','max:512'],
        ]);

        $post = new Post();
        $post->title = $data['title'];
        $post->slug  = $data['slug'] ?: Str::slug($data['title']);

        // ảnh: ưu tiên file upload
        if ($r->hasFile('image')) {
            $path = $r->file('image')->store('posts','public');
            $post->image_url = url('storage/'.$path);
        } else {
            $post->image_url = isset($data['image_url']) ? self::toAbsolute($data['image_url']) : null;
        }

        $post->summary     = $data['summary'] ?? ($r->input('excerpt') ?: null);
        $post->content     = $data['content']   ?? null;
        $post->status      = array_key_exists('status',$data) ? (int)$data['status'] : 1;
        $post->category_id = $data['category_id'] ?? null;

        if ($r->filled('published_at') && Schema::hasColumn($post->getTable(),'published_at')) {
            $post->published_at = Carbon::parse($r->input('published_at'));
        }

        /* === THÊM: gán created_by & updated_by === */
        $userId = optional(Auth::user())->id ?? 0;
        if (Schema::hasColumn($post->getTable(),'created_by'))  $post->created_by  = $userId;
        if (Schema::hasColumn($post->getTable(),'updated_by'))  $post->updated_by  = $userId;
        /* ========================================= */

        $post->save();

        return response()->json($post, 201);
    }

    // POST/PUT/PATCH /api/admin/posts/{id}
    public function update(Request $r, $id)
    {
        $post = Post::findOrFail((int)$id);
        $table = (new Post)->getTable();

        $data = $r->validate([
            'title'       => ['sometimes','required','string','max:255'],
            'slug'        => ['nullable','string','max:255', Rule::unique($table,'slug')->ignore($post->id)],
            'summary'     => ['nullable','string','max:512'],
            'content'     => ['nullable','string'],
            'status'      => ['nullable','integer','in:0,1'],
            'category_id' => ['nullable','integer'],
            'published_at'=> ['nullable','date'],
            'image'       => ['nullable','image','max:2048'],
            'image_url'   => ['nullable','string','max:512'],
        ]);

        if (array_key_exists('title',$data)) $post->title = $data['title'];
        if (array_key_exists('slug',$data))  $post->slug  = $data['slug'] ?: Str::slug($post->title);
        if (array_key_exists('summary',$data)) $post->summary = $data['summary'];
        if (!$r->has('summary') && $r->filled('excerpt')) $post->summary = $r->input('excerpt');
        if (array_key_exists('content',$data)) $post->content = $data['content'];
        if (array_key_exists('status',$data))  $post->status  = (int)$data['status'];
        if (array_key_exists('category_id',$data)) $post->category_id = $data['category_id'];

        if ($r->hasFile('image')) {
            if ($post->image_url && str_contains($post->image_url, '/storage/')) {
                $rel = Str::after($post->image_url, url('storage/').'/');
                if ($rel && Storage::disk('public')->exists($rel)) {
                    Storage::disk('public')->delete($rel);
                }
            }
            $path = $r->file('image')->store('posts','public');
            $post->image_url = url('storage/'.$path);
        } elseif (array_key_exists('image_url',$data) && $data['image_url']) {
            $post->image_url = self::toAbsolute($data['image_url']);
        }

        if ($r->filled('published_at') && Schema::hasColumn($post->getTable(),'published_at')) {
            $post->published_at = Carbon::parse($r->input('published_at'));
        }

        /* === THÊM: gán updated_by === */
        $userId = optional(Auth::user())->id ?? 0;
        if (Schema::hasColumn($post->getTable(),'updated_by'))  $post->updated_by  = $userId;
        /* ========================================= */

        $post->save();

        return response()->json($post);
    }

    // DELETE /api/admin/posts/{id}
    public function destroy($id)
    {
        $post = Post::findOrFail((int)$id);

        if ($post->image_url && str_contains($post->image_url, '/storage/')) {
            $rel = Str::after($post->image_url, url('storage/').'/');
            if ($rel && Storage::disk('public')->exists($rel)) {
                Storage::disk('public')->delete($rel);
            }
        }

        $post->delete();
        return response()->json(['message' => 'deleted']);
    }
}
