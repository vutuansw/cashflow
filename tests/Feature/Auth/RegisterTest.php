<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\System\GroupService;
use App\Models\User;
use App\Support\CurrentGroup;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshBothDatabases;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nguyễn Văn An',
            'phone' => '0988 777 222',
            'email' => 'an@example.com',
            'password' => 'matkhau123',
            'group_name' => 'Chi đoàn A12',
            'group_code' => 'chidoan-a12',
            'visibility' => 'private',
            'opening_balance' => 2500000,
        ];
    }

    public function test_dang_ky_tao_tai_khoan_nhom_va_dang_nhap_luon(): void
    {
        $this->postJson('/api/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('group.code', 'chidoan-a12')
            ->assertJsonPath('me.groups.0.role', 'owner');

        $this->assertAuthenticated();

        $user = User::wherePhone('0988777222')->firstOrFail();
        $group = Group::where('code', 'chidoan-a12')->firstOrFail();

        $this->assertSame($user->id, $group->owner_id);
        $this->assertSame('private', $group->visibility);
        $this->assertSame(2500000, $group->opening_balance);
        $this->assertSame('owner', GroupMember::where('group_id', $group->id)->value('role')->value);
        $this->assertSame('pending_review', GroupService::where('group_id', $group->id)->value('review_status'));

        app(CurrentGroup::class)->set($group);
        $this->assertSame(['Đóng quỹ năm'], Category::where('type', 'income')->pluck('name')->all());
        $this->assertSame(['Liên hoan', 'Đám hiếu', 'Đám hỉ', 'Thăm ốm', 'Thăm đẻ'], Category::where('type', 'expense')->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame($user->id, Contact::sole()->user_id);
    }

    public function test_sdt_da_co_tai_khoan_thi_bao_dang_nhap(): void
    {
        User::factory()->create(['phone' => '0988777222']);

        $this->postJson('/api/register', $this->payload(['phone' => '+84988777222']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_ma_nhom_trung_hoac_danh_rieng_bi_tu_choi(): void
    {
        Group::factory()->create(['code' => 'chidoan-a12']);

        $this->postJson('/api/register', $this->payload())->assertJsonValidationErrors('group_code');
        $this->postJson('/api/register', $this->payload(['group_code' => 'system']))->assertJsonValidationErrors('group_code');
        $this->postJson('/api/register', $this->payload(['group_code' => 'Chi Doan']))->assertJsonValidationErrors('group_code');
    }

    public function test_o_an_chong_bot(): void
    {
        $this->postJson('/api/register', $this->payload(['website' => 'http://spam']))
            ->assertJsonValidationErrors('website');
        $this->assertGuest();
    }

    public function test_kiem_tra_ma_nhom_va_goi_y(): void
    {
        Group::factory()->create(['code' => 'chi-doan-a12']);

        $this->getJson('/api/group-code?'.http_build_query(['code' => 'chi-doan-a12', 'name' => 'Chi đoàn A12']))
            ->assertOk()
            ->assertJson(['available' => false, 'suggestion' => 'chi-doan-a12-2']);

        $this->getJson('/api/group-code?code=nhomabc')->assertJson(['available' => true]);
    }
}
