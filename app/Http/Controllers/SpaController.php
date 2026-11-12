<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

/**
 * Trả khung SPA. Với trang nhóm thì thêm thẻ Open Graph (tên nhóm, ảnh bìa)
 * để dán link vào Zalo/Facebook ra bản xem trước đẹp. Mọi trang trừ trang chủ
 * đều noindex: "công khai" nghĩa là ai có link, không phải để Google tìm.
 * Xem plan/03 mục 4, ảnh share xem plan/29.
 */
class SpaController extends Controller
{
    public function __invoke(Request $request)
    {
        $meta = [
            'title' => config('cashflow.brand.tagline').' - '.config('cashflow.brand.name'),
            'description' => config('cashflow.brand.description'),
            'image' => null,
            'image_type' => null,
            'image_alt' => null,
            'noindex' => $request->path() !== '/',
            // Nút "Xem nhóm mẫu" ở trang chủ (plan/14). In ở MỌI trang chứ không riêng "/":
            // mở app ở /login hay trang nhóm rồi mới về trang chủ thì SPA vẫn đọc meta của trang đầu.
            'demo_group' => config('cashflow.landing.demo_group'),
        ];

        // Trang chủ là landing page (plan/14): mô tả dài hơn, ảnh chia sẻ riêng.
        if ($request->path() === '/') {
            $meta['description'] = config('cashflow.landing.description');
            $meta = $this->brandImage($meta);
        }

        $segment = strtolower((string) $request->segment(1));
        if ($segment !== '' && preg_match(config('cashflow.group_code.pattern'), $segment)
            && ! in_array($segment, config('cashflow.group_code.reserved'), true)) {
            $group = Group::query()->where('code', $segment)->first();
            if ($group !== null) {
                $meta = $this->groupMeta($meta, $group);
                if ($request->segment(2) === 'members' && is_string($handle = $request->segment(3))) {
                    $meta = $this->memberMeta($meta, $group, $handle);
                }
            }
        }

        return response()->view('app', ['meta' => $meta]);
    }

    /**
     * Trang nhóm và mọi link con của nhóm: tên nhóm, ảnh bìa nhóm (JPEG 1200×630).
     * Nhóm private vẫn hiện ảnh bìa (ảnh bìa vốn công khai), chỉ giấu mô tả.
     */
    private function groupMeta(array $meta, Group $group): array
    {
        $meta['title'] = $group->name.' - '.config('cashflow.brand.name');
        $meta['description'] = $group->isPublic()
            ? ($group->description ?: 'Thu chi quỹ '.$group->name.', minh bạch theo dòng thời gian.')
            : 'Nhóm riêng tư trên '.config('cashflow.brand.name').'.';

        $share = MediaUrl::groupShare($group);

        return $share === null
            ? $this->brandImage($meta, $group->name)
            : ['image' => url($share), 'image_type' => 'image/jpeg', 'image_alt' => $group->name] + $meta;
    }

    /**
     * Trang một thành viên: tên và ảnh của người đó (ảnh đại diện, không có thì ảnh bìa cá nhân,
     * không có nữa thì giữ ảnh nhóm). Nhóm private, mã sai hoặc người đã rời nhóm thì giữ nguyên
     * như trang nhóm: khách không xem được trang đó nên link cũng không được lộ ai ở trong nhóm.
     */
    private function memberMeta(array $meta, Group $group, string $handle): array
    {
        if (! $group->isPublic() || ! preg_match('/^'.GroupMember::HANDLE_PATTERN.'$/', $handle)) {
            return $meta;
        }

        $user = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('handle', $handle)
            ->where('status', 'active')
            ->with('user')
            ->first()?->user;
        if ($user === null) {
            return $meta;
        }

        $meta['title'] = $user->name.' · '.$group->name.' - '.config('cashflow.brand.name');
        $meta['description'] = $user->bio ?: 'Thành viên nhóm '.$group->name.'.';

        $share = MediaUrl::profileShare($user, 'avatar') ?? MediaUrl::profileShare($user, 'cover');
        if ($share !== null) {
            $meta = ['image' => url($share), 'image_type' => 'image/jpeg', 'image_alt' => $user->name] + $meta;
        }

        return $meta;
    }

    /** Ảnh chung của WePo (1200×630), dùng ở trang chủ và nhóm chưa có ảnh bìa. */
    private function brandImage(array $meta, ?string $alt = null): array
    {
        return [
            'image' => url(config('cashflow.landing.image')),
            'image_type' => 'image/png',
            'image_alt' => $alt ?? config('cashflow.brand.name'),
        ] + $meta;
    }
}
