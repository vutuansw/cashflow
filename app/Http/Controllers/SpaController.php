<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

/**
 * Trả khung SPA. Với trang nhóm thì thêm thẻ Open Graph (tên nhóm, ảnh bìa)
 * để dán link vào Zalo/Facebook ra bản xem trước đẹp. Mọi trang trừ trang chủ
 * đều noindex: "công khai" nghĩa là ai có link, không phải để Google tìm.
 * Xem plan/03 mục 4.
 */
class SpaController extends Controller
{
    public function __invoke(Request $request)
    {
        $meta = [
            'title' => config('cashflow.brand.tagline').' - '.config('cashflow.brand.name'),
            'description' => config('cashflow.brand.description'),
            'image' => null,
            'noindex' => $request->path() !== '/',
        ];

        // Trang chủ là landing page (plan/14): mô tả dài hơn, ảnh chia sẻ riêng, nút xem nhóm mẫu.
        if ($request->path() === '/') {
            $meta['description'] = config('cashflow.landing.description');
            $meta['image'] = url(config('cashflow.landing.image'));
            $meta['demo_group'] = config('cashflow.landing.demo_group');
        }

        $segment = strtolower((string) $request->segment(1));
        if ($segment !== '' && preg_match(config('cashflow.group_code.pattern'), $segment)
            && ! in_array($segment, config('cashflow.group_code.reserved'), true)) {
            $group = Group::query()->where('code', $segment)->first();
            if ($group !== null) {
                $meta['title'] = $group->name.' - '.config('cashflow.brand.name');
                $meta['description'] = $group->isPublic()
                    ? ($group->description ?: 'Thu chi quỹ '.$group->name.', minh bạch theo dòng thời gian.')
                    : 'Nhóm riêng tư trên '.config('cashflow.brand.name').'.';
                $cover = MediaUrl::groupCover($group);
                $meta['image'] = $cover ? url($cover) : null;
            }
        }

        return response()->view('app', ['meta' => $meta]);
    }
}
