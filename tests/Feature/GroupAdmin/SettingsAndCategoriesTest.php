<?php

namespace Tests\Feature\GroupAdmin;

use App\Enums\GroupRole;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class SettingsAndCategoriesTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    public function test_cap_nhat_cai_dat_nhom_va_ma_hoa_stk(): void
    {
        $group = $this->makeGroup();
        $admin = $this->memberOf($group, GroupRole::Admin);

        $this->actingAs($admin)->putJson($this->adminUrl($group, 'settings'), [
            'name' => 'Chi đoàn A12 K45',
            'visibility' => 'private',
            'opening_balance' => 1500000,
            'bank_code' => 'MBBank',
            'bank_account_no' => '0123456789',
            'bank_account_name' => 'nguyen van an',
            'notify_emails' => ['thuquy@example.com'],
        ])->assertOk()
            ->assertJsonPath('bank_account_name', 'NGUYEN VAN AN')
            ->assertJsonPath('bank_account_no', '0123456789')
            ->assertJsonPath('my_role', 'admin');

        $this->assertStringNotContainsString('0123456789', (string) DB::table('groups')->where('id', $group->id)->value('bank_account_no'));
    }

    public function test_xoa_nhom_can_go_dung_ma(): void
    {
        $group = $this->makeGroup(['code' => 'nhom-xoa']);
        $this->actingAs($this->ownerOf($group));

        $this->deleteJson($this->adminUrl($group), ['confirm_code' => 'sai'])->assertJsonValidationErrors('confirm_code');
        $this->deleteJson($this->adminUrl($group), ['confirm_code' => 'nhom-xoa'])->assertNoContent();
        $this->assertSoftDeleted($group);
        $this->getJson($this->adminUrl($group, 'settings'))->assertNotFound();
    }

    public function test_loai_thu_chi_them_sua_an_sap_xep_xoa(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $this->getJson($this->adminUrl($group, 'categories'))->assertJsonCount(6);

        $id = $this->postJson($this->adminUrl($group, 'categories'), ['type' => 'expense', 'name' => 'Mua quà', 'icon' => 'gift', 'color' => '#ff0000'])
            ->assertCreated()->json('id');
        $this->postJson($this->adminUrl($group, 'categories'), ['type' => 'expense', 'name' => 'Mua quà'])->assertJsonValidationErrors('name');
        // Trùng tên nhưng khác loại thu/chi thì được.
        $this->postJson($this->adminUrl($group, 'categories'), ['type' => 'income', 'name' => 'Mua quà'])->assertCreated();

        $this->putJson($this->adminUrl($group, "categories/{$id}"), ['name' => 'Quà tặng', 'archived' => true])
            ->assertOk()->assertJson(['name' => 'Quà tặng', 'archived' => true]);

        $ids = collect($this->getJson($this->adminUrl($group, 'categories'))->json())->where('type', 'expense')->pluck('id')->reverse()->values()->all();
        $sorted = $this->postJson($this->adminUrl($group, 'categories/reorder'), ['ids' => $ids])->json();
        $this->assertSame($ids, collect($sorted)->where('type', 'expense')->pluck('id')->values()->all());

        $this->deleteJson($this->adminUrl($group, "categories/{$id}"))->assertNoContent();
    }

    public function test_khong_dung_loai_cua_nhom_khac(): void
    {
        $a = $this->makeGroup();
        $b = $this->makeGroup();
        $categoryB = Category::acrossGroups()->where('group_id', $b->id)->firstOrFail();

        $this->actingAs($this->ownerOf($a))
            ->putJson($this->adminUrl($a, "categories/{$categoryB->id}"), ['name' => 'Hack'])
            ->assertNotFound();
    }
}
