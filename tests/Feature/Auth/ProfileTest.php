<?php

namespace Tests\Feature\Auth;

use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Trang cá nhân: sửa thông tin, ảnh đại diện, ảnh bìa. Xem plan/08. */
class ProfileTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    private function upload(string $kind, UploadedFile $file)
    {
        return $this->post('/api/me/images', ['kind' => $kind, 'image' => $file], ['Accept' => 'application/json']);
    }

    public function test_sua_ho_ten_email_va_gioi_thieu(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/api/me', ['name' => 'Vũ Đức Tuấn', 'email' => 'tuan@example.com', 'bio' => 'Thủ quỹ chi đoàn'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Vũ Đức Tuấn')
            ->assertJsonPath('data.bio', 'Thủ quỹ chi đoàn');

        $this->putJson('/api/me', ['name' => 'Tuấn', 'bio' => str_repeat('a', 161)])->assertJsonValidationErrors('bio');
        $this->assertSame('Thủ quỹ chi đoàn', $user->fresh()->bio);
    }

    public function test_tai_anh_dai_dien_cat_vuong_va_xoa_anh_cu(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = $this->upload('avatar', UploadedFile::fake()->image('a.jpg', 1200, 800))->assertOk()->json('data.avatar_url');
        $firstPath = $user->fresh()->avatar_path;
        $this->assertStringStartsWith("users/{$user->id}/profile/", $firstPath);
        [$w, $h] = getimagesizefromstring(Storage::disk('media')->get($firstPath));
        $this->assertSame([400, 400], [$w, $h]);

        $second = $this->upload('avatar', UploadedFile::fake()->image('b.png', 600, 600))->assertOk()->json('data.avatar_url');
        $this->assertNotSame($first, $second);
        Storage::disk('media')->assertMissing($firstPath);
        Storage::disk('media')->assertExists($user->fresh()->avatar_path);
    }

    public function test_tai_anh_bia_ti_le_3_1_va_go_anh(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->upload('cover', UploadedFile::fake()->image('cover.jpg', 2400, 1600))->assertOk();
        $path = $user->fresh()->cover_path;
        [$w, $h] = getimagesizefromstring(Storage::disk('media')->get($path));
        $this->assertSame([1600, 533], [$w, $h]);

        $this->deleteJson('/api/me/images/cover')->assertOk()->assertJsonPath('data.cover_url', null);
        Storage::disk('media')->assertMissing($path);
        $this->assertNull($user->fresh()->cover_path);
    }

    public function test_tu_choi_file_khong_phai_anh_va_khach_chua_dang_nhap(): void
    {
        $this->upload('avatar', UploadedFile::fake()->image('a.jpg'))->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->upload('avatar', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->assertJsonValidationErrors('image');
        $this->upload('banner', UploadedFile::fake()->image('a.jpg'))->assertJsonValidationErrors('kind');
    }

    public function test_anh_ca_nhan_cong_khai_dung_ten_file_moi_tra(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $url = $this->upload('avatar', UploadedFile::fake()->image('a.jpg'))->json('data.avatar_url');
        auth()->logout();

        $response = $this->get($url)->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));

        $this->get(preg_replace('#/[^/]+$#', '/doan-mo', $url))->assertNotFound();
        $this->get(str_replace('/avatar/', '/cover/', $url))->assertNotFound();
        $this->get('/media/u/999999/avatar/abc')->assertNotFound();
    }

    public function test_me_tra_member_handle_tung_nhom_de_mo_trang_cua_minh(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup([], $user);
        $handle = GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->value('handle');

        $this->actingAs($user)->getJson('/api/me')->assertJsonPath('data.groups.0.member_handle', $handle);
        $this->assertArrayNotHasKey('member_id', $this->getJson('/api/me')->json('data.groups.0'));
        $this->getJson("/api/g/{$group->code}/members/{$handle}")->assertOk()->assertJsonPath('member.is_me', true);
    }
}
