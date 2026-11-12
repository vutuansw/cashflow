<?php

namespace Tests\Feature;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Thẻ Open Graph khi dán link vào Zalo/Messenger/Facebook: trang nào ra ảnh gì. Xem plan/29 mục 3, 4. */
class ShareMetaTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function assertImage(TestResponse $response, string $image, string $type = 'image/jpeg', ?string $alt = null): TestResponse
    {
        $response->assertOk()
            ->assertSee('<meta property="og:image" content="'.$image.'">', false)
            ->assertSee('<meta property="og:image:secure_url" content="'.$image.'">', false)
            ->assertSee('<meta property="og:image:type" content="'.$type.'">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta property="og:image:height" content="630">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="twitter:image" content="'.$image.'">', false);

        if ($alt !== null) {
            $response->assertSee('<meta property="og:image:alt" content="'.e($alt).'">', false);
        }

        return $response;
    }

    private function groupWithCover(string $visibility): Group
    {
        $group = $this->makeGroup(['name' => 'Chi đoàn A12', 'visibility' => $visibility]);
        $group->update(['cover_path' => "groups/{$group->id}/brand/2026/11/cover-abc123.webp"]);

        return $group;
    }

    private function handleOf(Group $group, User $user): string
    {
        return GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->value('handle');
    }

    public function test_trang_nhom_va_moi_link_con_ra_anh_bia_nhom(): void
    {
        $group = $this->groupWithCover('public');
        $image = url("/media/{$group->code}/brand/og/cover-abc123.jpg");
        $this->assertSame($image, url(MediaUrl::groupShare($group)));

        foreach (['', '/stats', '/campaigns/thu-quy-2026', '/members', '/register', '/admin/transactions'] as $path) {
            $this->assertImage($this->get("/{$group->code}{$path}"), $image, alt: 'Chi đoàn A12')
                ->assertSee('<meta property="og:title" content="Chi đoàn A12 - WePo">', false);
        }
    }

    public function test_nhom_rieng_tu_van_ra_anh_bia(): void
    {
        $group = $this->groupWithCover('private');

        $this->assertImage($this->get("/{$group->code}"), url("/media/{$group->code}/brand/og/cover-abc123.jpg"))
            ->assertSee('Nhóm riêng tư trên WePo.', false);
    }

    public function test_nhom_chua_co_anh_bia_ra_anh_chung_wepo(): void
    {
        $group = $this->makeGroup(['name' => 'Nhóm chưa bìa']);

        $this->assertImage($this->get("/{$group->code}"), url('/landing/og.png'), 'image/png', 'Nhóm chưa bìa');
        $this->assertImage($this->get('/'), url('/landing/og.png'), 'image/png', 'WePo');
    }

    public function test_trang_khong_thuoc_nhom_khong_co_anh(): void
    {
        foreach (['/login', '/groups/new', '/khong-co-nhom'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('og:image', false)->assertDontSee('twitter:card', false);
        }
    }

    public function test_link_thanh_vien_nhom_cong_khai_ra_ten_va_anh_nguoi_do(): void
    {
        $group = $this->groupWithCover('public');
        $lan = $this->memberOf($group, GroupRole::Member, ['name' => 'Trần Thị Lan', 'bio' => 'Thủ quỹ vui tính']);
        $lan->update(['avatar_path' => "users/{$lan->id}/profile/2026/11/avatar-lan1.webp", 'cover_path' => "users/{$lan->id}/profile/2026/11/cover-lan2.webp"]);
        $url = "/{$group->code}/members/".$this->handleOf($group, $lan);

        // Có ảnh đại diện: dùng ảnh đại diện.
        $this->assertImage($this->get($url), url("/media/u/{$lan->id}/og/avatar/avatar-lan1.jpg"), alt: 'Trần Thị Lan')
            ->assertSee('<meta property="og:title" content="Trần Thị Lan · Chi đoàn A12 - WePo">', false)
            ->assertSee('<meta property="og:description" content="Thủ quỹ vui tính">', false);

        // Không có ảnh đại diện: ảnh bìa cá nhân.
        $lan->update(['avatar_path' => null]);
        $this->assertImage($this->get($url), url("/media/u/{$lan->id}/og/cover/cover-lan2.jpg"));

        // Không có ảnh nào: ảnh nhóm, nhưng tiêu đề vẫn là tên người.
        $lan->update(['cover_path' => null, 'bio' => null]);
        $this->assertImage($this->get($url), url("/media/{$group->code}/brand/og/cover-abc123.jpg"))
            ->assertSee('<meta property="og:title" content="Trần Thị Lan · Chi đoàn A12 - WePo">', false)
            ->assertSee('<meta property="og:description" content="Thành viên nhóm Chi đoàn A12.">', false);
    }

    public function test_link_thanh_vien_nhom_rieng_tu_ma_sai_hoac_da_roi_nhom_chi_ra_nhom(): void
    {
        $private = $this->groupWithCover('private');
        $lan = $this->memberOf($private, GroupRole::Member, ['name' => 'Trần Thị Lan']);
        $lan->update(['avatar_path' => "users/{$lan->id}/profile/2026/11/avatar-lan1.webp"]);

        $this->assertImage($this->get("/{$private->code}/members/".$this->handleOf($private, $lan)), url("/media/{$private->code}/brand/og/cover-abc123.jpg"))
            ->assertSee('<meta property="og:title" content="Chi đoàn A12 - WePo">', false)
            ->assertDontSee('Trần Thị Lan', false);

        $public = $this->groupWithCover('public');
        $an = $this->memberOf($public, GroupRole::Member, ['name' => 'Lê Văn An']);
        $groupImage = url("/media/{$public->code}/brand/og/cover-abc123.jpg");

        $this->assertImage($this->get("/{$public->code}/members/zzzzzzzzzz"), $groupImage);
        $this->assertImage($this->get("/{$public->code}/members/".$this->handleOf($private, $lan)), $groupImage)
            ->assertDontSee('Trần Thị Lan', false);

        GroupMember::query()->where('group_id', $public->id)->where('user_id', $an->id)->update(['status' => 'removed']);
        $this->assertImage($this->get("/{$public->code}/members/".$this->handleOf($public, $an)), $groupImage)
            ->assertDontSee('Lê Văn An', false);
    }
}
