<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AIChatController extends Controller
{
    public function chat(Request $req)
    {
        try {
            $userMsg = (string) $req->input('message', '');
            if (trim($userMsg) === '') {
                return response()->json(['reply' => ''], 200);
            }

            $msgNorm = $this->norm($userMsg);

            // 0) Small talk / Tên shop / Sản phẩm hiện có
            if ($this->looksLikeGreeting($msgNorm)) {
                return response()->json(['reply' => $this->answerGreeting()], 200);
            }
            if ($this->looksLikeShopName($msgNorm)) {
                return response()->json(['reply' => $this->getShopName()], 200);
            }
            if ($this->looksLikeWhatProducts($msgNorm)) {
                return response()->json(['reply' => $this->answerWhatProducts()], 200);
            }

            // 1) Giờ / Ngày / Thứ
            if ($this->looksLikeTimeIntent($msgNorm)) {
                return response()->json(['reply' => $this->answerDatetime()], 200);
            }

            // 2) Giá sản phẩm (min/max/avg)
            if ($this->looksLikePriceIntent($msgNorm)) {
                $stats = $this->queryProductPriceStats();
                if (!$stats) return response()->json(['reply' => 'Chưa có dữ liệu giá.'], 200);
                $fmt = fn($v) => number_format((float)$v, 0, ',', '.') . 'đ';
                $reply =
                    "SP: {$stats['total']} | ".
                    "Thấp nhất: ".$fmt($stats['min_price'])." | ".
                    "Cao nhất: ".$fmt($stats['max_price'])." | ".
                    "TB: ".$fmt($stats['avg_price']);
                return response()->json(['reply' => $reply], 200);
            }

            // 3) Bán chạy
            if ($this->looksLikeBestSellerIntent($msgNorm)) {
                $days = $this->parseDaysWindow($msgNorm) ?? 90;
                $best = $this->queryBestSellers(5, $days);
                if (!$best) return response()->json(['reply' => 'Chưa có dữ liệu bán chạy.'], 200);

                $cards = array_map(fn($r) => $r['card'], $best);
                $title = "Top bán chạy $days ngày";
                return response()->json(['reply' => $title, 'cards' => $cards], 200);
            }

            // 4) Gợi ý (chỉ khi người dùng chủ động hỏi)
            if ($this->looksLikeSuggestIntent($msgNorm)) {
                $suggest = $this->querySuggestedProducts(6);
                if (!$suggest) return response()->json(['reply' => 'Chưa có gợi ý phù hợp.'], 200);
                $cards = array_map(fn($r) => $r['card'], $suggest);
                return response()->json(['reply' => 'Gợi ý sản phẩm', 'cards' => $cards], 200);
            }

            // 5) Fallback: STRICT -> trả ngắn gọn
            return response()->json(['reply' => $this->answerFallback($userMsg)], 200);

        } catch (\Throwable $e) {
            Log::error('AI /api/ai/chat failed', ['err' => $e->getMessage()]);
            return response()->json(['reply' => ''], 200);
        }
    }

    /* ======= STRICT MODE ======= */
    private function isStrict(): bool
    {
        return (bool) config('aichat.strict_mode', true);
    }

    /* ======= CHUẨN HÓA ======= */
    private function norm(string $s): string
    {
        $s = Str::lower($s);
        $find = ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ','đ','é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ','í','ì','ỉ','ĩ','ị','ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ','ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự','ý','ỳ','ỷ','ỹ','ỵ'];
        $rep  = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','d','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y'];
        $s = str_replace($find, $rep, $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }
    private function ncontains(string $hay, array $needles): bool
    {
        $h = $this->norm($hay);
        foreach ($needles as $n) {
            if (Str::contains($h, $this->norm($n))) return true;
        }
        return false;
    }

    /* ======= INTENT ======= */
    private function looksLikeGreeting(string $q): bool
    {
        return $this->ncontains($q, ['chào','xin chào','hello','hi','alo']);
    }
    private function looksLikeShopName(string $q): bool
    {
        return $this->ncontains($q, ['shop bạn tên gì','ten shop','ten cua hang','shop ten gi','ten ben ban']);
    }
    private function looksLikeWhatProducts(string $q): bool
    {
        if ($this->ncontains($q, [
            'shop nhung san pham nao','shop co nhung san pham nao','nhung san pham nao',
            'san pham nao','danh sach san pham','san pham hien co','shop co gi','co gi ban',
            'danh muc nao','category nao'
        ])) return true;

        $n = $this->norm($q);
        if (preg_match('/\bsan pham\b/u', $n) && preg_match('/\b(nao|gi)\b/u', $n)) return true;
        return false;
    }
    private function looksLikeTimeIntent(string $q): bool
    {
        return $this->ncontains($q, [
            'may gio','gio hien tai','bay gio la may gio',
            'hom nay la ngay may','ngay thang','thoi gian bay gio',
            'gio bay gio','ngay bay gio','nay thu may','hom nay thu may','thu may'
        ]);
    }
    private function looksLikePriceIntent(string $q): bool
    {
        $hasKey = $this->ncontains($q, ['gia','gia ban','gia thanh','price']);
        $type   = $this->ncontains($q, ['cao nhat','dat nhat','max','thap nhat','re nhat','min','trung binh','average','avg']);
        return $hasKey && $type;
    }
    private function looksLikeBestSellerIntent(string $q): bool
    {
        return $this->ncontains($q, ['ban chay','best seller','bestseller','top ban','nhieu don','nhieu luot mua','mua nhieu']);
    }
    private function looksLikeSuggestIntent(string $q): bool
    {
        return $this->ncontains($q, ['goi y','goi i','goi y san pham','de xuat','recommend','suggest','nen mua','phu hop']);
    }

    /* ======= SMALL TALK / DANH MỤC / MỚI NHẤT ======= */
    private function getShopName(): string
    {
        return (string) config('aichat.shop_name', 'THETHAO SPORTS');
    }
    private function answerGreeting(): string
    {
        return $this->isStrict() ? 'Chào bạn.' : "Chào bạn! Mình là trợ lý của ".$this->getShopName().".";
    }
    private function answerWhatProducts(): string
    {
        $catLines = [];
        if (Schema::hasTable('ptdt_category') && Schema::hasColumn('ptdt_category', 'name')) {
            $cats = DB::table('ptdt_category')->select('id','name','slug')->orderBy('id','desc')->limit(5)->get();
            foreach ($cats as $c) {
                $u = $this->buildCategoryUrl($c->slug);
                $catLines[] = $u ? ($c->name.' → '.$u) : $c->name;
            }
        }
        $prdLines = [];
        foreach ($this->queryNewestProducts(5) as $p) {
            $price = $p['price'] !== null ? number_format((float)$p['price'], 0, ',', '.') . 'đ' : '—';
            $line  = $p['name'].' ('.$price.')';
            if ($p['url']) $line .= ' → '.$p['url'];
            $prdLines[] = $line;
        }
        if ($this->isStrict()) {
            $parts = [];
            if ($catLines) $parts[] = 'Danh mục: '.implode(' | ', $catLines);
            if ($prdLines) $parts[] = 'SP mới: '.implode(' | ', $prdLines);
            return $parts ? implode(' | ', $parts) : 'Chưa có dữ liệu.';
        }
        $out = [];
        if ($catLines) $out[] = "Danh mục:\n- ".implode("\n- ", $catLines);
        if ($prdLines) $out[] = "Sản phẩm mới:\n- ".implode("\n- ", $prdLines);
        return $out ? implode("\n\n", $out) : 'Chưa có dữ liệu.';
    }

    /* ======= THỜI GIAN ======= */
    private function answerDatetime(): string
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $weekdayMap = [0=>'Chủ nhật',1=>'Thứ hai',2=>'Thứ ba',3=>'Thứ tư',4=>'Thứ năm',5=>'Thứ sáu',6=>'Thứ bảy'];
        $thu = $weekdayMap[(int)$now->dayOfWeek];
        $open  = config('aichat.shop_open');
        $close = config('aichat.shop_close');
        $base  = "{$thu}, {$now->format('d/m/Y')} — {$now->format('H:i')}";

        if ($this->isStrict()) return $base;
        if ($open && $close) {
            $nowH = $now->format('H:i');
            $in = ($nowH >= $open && $nowH <= $close) ? 'trong' : 'ngoài';
            return $base." (đang {$in} giờ làm việc {$open}–{$close})";
        }
        return $base;
    }

    /* ======= GIÁ ======= */
    private function getPriceColumns(): array
    {
        return [config('aichat.product_price_col'), config('aichat.product_sale_price_col')];
    }
    private function buildPriceExpr(array $cols): array
    {
        [$envPrice, $envSale] = $this->getPriceColumns();
        $priceCol = null; $saleCol  = null;
        if ($envPrice && in_array($envPrice, $cols)) $priceCol = $envPrice;
        if ($envSale  && in_array($envSale,  $cols)) $saleCol  = $envSale;
        if (!$priceCol) foreach (['price_root','price'] as $c) if (in_array($c, $cols)) { $priceCol = $c; break; }
        if (!$saleCol && in_array('price_sale', $cols)) $saleCol = 'price_sale';
        if (!$priceCol && !$saleCol) return [null, null, null];

        $expr = $saleCol
            ? "CASE WHEN {$saleCol} IS NOT NULL AND {$saleCol} > 0 THEN {$saleCol} ELSE ".($priceCol ?: $saleCol)." END"
            : ($priceCol ?? $saleCol);
        return [$expr, $priceCol, $saleCol];
    }
    private function queryProductPriceStats(): ?array
    {
        $t = 'ptdt_product';
        if (!Schema::hasTable($t)) return null;
        [$expr] = $this->buildPriceExpr(Schema::getColumnListing($t));
        if (!$expr) return null;
        $r = DB::table($t)->selectRaw("COUNT(*) total, MIN($expr) min_price, MAX($expr) max_price, AVG($expr) avg_price")->first();
        if (!$r || !$r->total) return null;
        return ['total'=>(int)$r->total,'min_price'=>(float)$r->min_price,'max_price'=>(float)$r->max_price,'avg_price'=>(float)$r->avg_price];
    }

    /* ======= URL & ẢNH ======= */
    private function buildProductUrl(?string $slug): ?string
    {
        if (!$slug) return null;
        $origin = (string) config('aichat.frontend_origin', '');
        $tpl    = (string) config('aichat.frontend_product_path', '/san-pham/{slug}');
        $path   = str_replace('{slug}', $slug, $tpl);
        if ($origin === '') return $path;
        if ($path && $path[0] !== '/') $path = '/'.$path;
        return rtrim($origin, '/') . $path;
    }
    private function buildCategoryUrl(?string $slug): ?string
    {
        if (!$slug) return null;
        $origin = (string) config('aichat.frontend_origin', '');
        $tpl    = (string) config('aichat.frontend_category_path', '/danh-muc/{slug}');
        $path   = str_replace('{slug}', $slug, $tpl);
        if ($origin === '') return $path;
        if ($path && $path[0] !== '/') $path = '/'.$path;
        return rtrim($origin, '/') . $path;
    }
    private function buildImageUrl(?string $path): ?string
    {
        if (!$path || !is_string($path)) return null;
        if (Str::startsWith($path, ['http://','https://'])) return $path;
        $origin = (string) config('aichat.asset_origin', '');
        $path = ltrim($path, '/');
        return $origin === '' ? '/'.$path : (rtrim($origin,'/').'/'.$path);
    }

    /* ======= BEST SELLERS ======= */
    private function getDoneStatusCodes(): array
    {
        return array_map('intval', (array) config('aichat.order_status_done', [4]));
    }
    private function queryBestSellers(int $limit = 5, ?int $days = 90): array
    {
        $od='ptdt_orderdetail'; $o='ptdt_order'; $p='ptdt_product';
        if (!Schema::hasTable($od) || !Schema::hasTable($o) || !Schema::hasTable($p)) return [];
        $done = $this->getDoneStatusCodes();

        $q = DB::table("$od as od")
            ->join("$o as o", 'o.id', '=', 'od.order_id')
            ->join("$p as pr",'pr.id','=','od.product_id')
            ->whereIn('o.status', $done);

        if ($days && $days > 0) $q->where('o.created_at','>=', Carbon::now()->subDays($days));

        [$priceExpr] = $this->buildPriceExpr(Schema::getColumnListing($p));
        $priceExpr = $priceExpr ?: 'NULL';

        $rows = $q->groupBy('od.product_id','pr.name','pr.slug','pr.thumbnail')
            ->selectRaw("
                od.product_id, pr.name, pr.slug, pr.thumbnail,
                SUM(od.qty) total_qty, SUM(od.amount) total_amount,
                $priceExpr as current_price
            ")
            ->orderByDesc('total_qty')
            ->limit($limit)->get();

        return $rows->map(function ($r) {
            $price = $r->current_price !== null ? (float)$r->current_price : null;
            $url   = $this->buildProductUrl($r->slug);
            $img   = $this->buildImageUrl($r->thumbnail);
            return [
                'product_id'    => (int)$r->product_id,
                'name'          => $r->name,
                'slug'          => $r->slug,
                'thumbnail'     => $r->thumbnail,
                'image'         => $img,
                'total_qty'     => (int)$r->total_qty,
                'total_amount'  => (float)$r->total_amount,
                'current_price' => $price,
                'url'           => $url,
                'card'          => [
                    'title'    => $r->name,
                    'subtitle' => $price !== null ? number_format($price, 0, ',', '.') . 'đ' : null,
                    'image'    => $img,
                    'url'      => $url,
                ],
            ];
        })->toArray();
    }

    /* ======= GỢI Ý ======= */
    private function querySuggestedProducts(int $limit = 6): array
    {
        $p='ptdt_product';
        if (!Schema::hasTable($p)) return [];
        [$priceExpr, $priceCol, $saleCol] = $this->buildPriceExpr(Schema::getColumnListing($p));
        $priceExpr = $priceExpr ?: ($priceCol ?: $saleCol ?: 'NULL');

        // on-sale
        $saleQ = DB::table($p)->select('id','name','slug','thumbnail')->selectRaw("$priceExpr as price");
        if ($saleCol) $saleQ->where($saleCol,'>',0); else $saleQ->whereRaw('1=0');
        $saleQ->orderByDesc('id')->limit($limit);

        $saleRows = $saleQ->get()->map(function($r){
            $img = $this->buildImageUrl($r->thumbnail);
            $url = $this->buildProductUrl($r->slug);
            $price = $r->price !== null ? (float)$r->price : null;
            return [
                'id'=>(int)$r->id,'name'=>$r->name,'slug'=>$r->slug,'thumbnail'=>$r->thumbnail,
                'image'=>$img,'price'=>$price,'url'=>$url,
                'card'=>['title'=>$r->name,'subtitle'=>$price!==null?number_format($price,0,',','.').'đ':null,'image'=>$img,'url'=>$url],
            ];
        })->toArray();

        if (count($saleRows) < $limit) {
            $need = $limit - count($saleRows);
            $ids  = array_column($saleRows, 'id');
            $newQ = DB::table($p)->select('id','name','slug','thumbnail')->selectRaw("$priceExpr as price")->orderByDesc('id')->limit($need);
            if ($ids) $newQ->whereNotIn('id', $ids);
            $newRows = $newQ->get()->map(function($r){
                $img = $this->buildImageUrl($r->thumbnail);
                $url = $this->buildProductUrl($r->slug);
                $price = $r->price !== null ? (float)$r->price : null;
                return [
                    'id'=>(int)$r->id,'name'=>$r->name,'slug'=>$r->slug,'thumbnail'=>$r->thumbnail,
                    'image'=>$img,'price'=>$price,'url'=>$url,
                    'card'=>['title'=>$r->name,'subtitle'=>$price!==null?number_format($price,0,',','.').'đ':null,'image'=>$img,'url'=>$url],
                ];
            })->toArray();
            $saleRows = array_merge($saleRows, $newRows);
        }
        return $saleRows;
    }

    private function queryNewestProducts(int $limit = 5): array
    {
        $p='ptdt_product';
        if (!Schema::hasTable($p)) return [];
        [$priceExpr, $priceCol, $saleCol] = $this->buildPriceExpr(Schema::getColumnListing($p));
        $priceExpr = $priceExpr ?: ($priceCol ?: $saleCol ?: 'NULL');

        $rows = DB::table($p)->select('id','name','slug','thumbnail')->selectRaw("$priceExpr as price")->orderByDesc('id')->limit($limit)->get();

        return $rows->map(function($r){
            $img = $this->buildImageUrl($r->thumbnail);
            $url = $this->buildProductUrl($r->slug);
            $price = $r->price !== null ? (float)$r->price : null;
            return [
                'id'=>(int)$r->id,'name'=>$r->name,'slug'=>$r->slug,'price'=>$price,'url'=>$url,'image'=>$img,
                'card'=>['title'=>$r->name,'subtitle'=>$price!==null?number_format($price,0,',','.').'đ':null,'image'=>$img,'url'=>$url],
            ];
        })->toArray();
    }

    /* ======= PARSER & FALLBACK ======= */
    private function parseDaysWindow(string $q): ?int
    {
        if (preg_match('/(\d+)\s*(ngay|d)\b/u', $q, $m)) return max(1, (int)$m[1]);
        if (preg_match('/(\d+)\s*(thang|m)\b/u', $q, $m)) return max(1, (int)$m[1]*30);
        if (preg_match('/trong\s+(\d+)\b/u', $q, $m) || preg_match('/(\d+)\s+gan\s+day/u', $q, $m)) return max(1, (int)$m[1]);
        return null;
    }
    private function answerFallback(string $userMsg): string
    {
        // STRICT: không gợi ý, không liệt kê gì thêm
        return $this->isStrict() ? 'Mình chưa hiểu câu này.' : "Mình chưa hiểu rõ câu này.";
    }
}
