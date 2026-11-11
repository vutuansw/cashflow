<?php

namespace Tests\Feature\GroupAdmin;

use App\Enums\GroupRole;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Nội quy nhóm: QTV viết trong Cài đặt, hiện ở trang nhóm theo quyền xem dòng thời gian. Xem plan/15. */
class GroupRulesTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    private function settings(array $overrides = []): array
    {
        return $overrides + ['name' => 'Chi đoàn A12', 'visibility' => 'public', 'opening_balance' => 0];
    }

    public function test_qtv_luu_noi_quy_va_trang_nhom_hien(): void
    {
        $group = $this->makeGroup(['visibility' => 'public']);
        $this->actingAs($this->memberOf($group, GroupRole::Admin));

        $rules = "Mỗi người đóng 500.000 đ/năm.\nHiếu hỉ hỗ trợ 500.000 đ.";
        $this->putJson($this->adminUrl($group, 'settings'), $this->settings(['rules' => "  {$rules}  "]))
            ->assertOk()->assertJsonPath('rules', $rules);
        $this->getJson($this->adminUrl($group, 'settings'))->assertJsonPath('rules', $rules);

        auth()->logout();
        $this->getJson("/api/g/{$group->code}/summary")->assertOk()->assertJsonPath('rules', $rules);
    }

    public function test_xoa_noi_quy_va_gioi_han_do_dai(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $this->putJson($this->adminUrl($group, 'settings'), $this->settings(['rules' => str_repeat('a', 3001)]))
            ->assertJsonValidationErrors('rules');
        $this->putJson($this->adminUrl($group, 'settings'), $this->settings(['rules' => 'Nội quy']))->assertOk();
        $this->putJson($this->adminUrl($group, 'settings'), $this->settings(['rules' => '']))
            ->assertOk()->assertJsonPath('rules', null);
    }

    public function test_nhom_rieng_tu_khong_lo_noi_quy_cho_khach(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $group->update(['rules' => 'Chỉ thành viên đọc']);

        $this->getJson("/api/g/{$group->code}/summary")->assertForbidden();
        $this->getJson("/api/g/{$group->code}")->assertOk()->assertJsonMissing(['rules' => 'Chỉ thành viên đọc']);

        $this->actingAs($this->memberOf($group, GroupRole::Member));
        $this->getJson("/api/g/{$group->code}/summary")->assertOk()->assertJsonPath('rules', 'Chỉ thành viên đọc');
    }
}
