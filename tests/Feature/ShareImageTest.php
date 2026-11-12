<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Services\Media\ShareImage;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Ảnh share (og:image) JPEG 1200×630 tạo từ ảnh bìa nhóm và ảnh cá nhân. Xem plan/29. */
class ShareImageTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    /** Ảnh đỏ, riêng 10% bên trái màu xanh lá: nhìn mép trái ảnh share là biết có bị cắt hay không. */
    private function stripedImage(int $width, int $height): UploadedFile
    {
        $gd = imagecreatetruecolor($width, $height);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 220, 30, 30));
        imagefilledrectangle($gd, 0, 0, (int) ($width * 0.1), $height, imagecolorallocate($gd, 30, 200, 30));
        $file = tempnam(sys_get_temp_dir(), 'og').'.png';
        imagepng($gd, $file);

        return new UploadedFile($file, 'cover.png', 'image/png', null, true);
    }

    private function uploadGroupCover(Group $group, UploadedFile $file): void
    {
        $this->actingAs($this->ownerOf($group))
            ->post($this->adminUrl($group, 'settings/images'), ['kind' => 'cover', 'image' => $file], ['Accept' => 'application/json'])
            ->assertOk();
        auth()->logout();
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgbAt(string $jpeg, int $x, int $y): array
    {
        $gd = imagecreatefromstring($jpeg);
        $c = imagecolorat($gd, $x, $y);

        return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
    }

    public function test_anh_share_nhom_jpeg_1200x630_giu_tron_anh_bia(): void
    {
        $group = $this->makeGroup(['visibility' => 'private']);
        $this->uploadGroupCover($group, $this->stripedImage(2400, 800));
        $group->refresh();

        $url = MediaUrl::groupShare($group);
        $this->assertSame("/media/{$group->code}/brand/og/".pathinfo($group->cover_path, PATHINFO_FILENAME).'.jpg', $url);

        // Khách chưa đăng nhập, nhóm private vẫn xem được (như ảnh bìa).
        $response = $this->get($url)->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));

        $jpeg = $response->getFile()->getContent();
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], array_slice(getimagesizefromstring($jpeg), 0, 3));

        // Ảnh bìa 3:1 nằm giữa (cao 400, từ y=115): mép trái vẫn còn dải xanh lá, tức là không bị cắt.
        [$r, $g] = $this->rgbAt($jpeg, 20, 315);
        $this->assertGreaterThan($r, $g);
        [$r, $g] = $this->rgbAt($jpeg, 900, 315);
        $this->assertGreaterThan($g, $r);

        // Lưu cạnh ảnh gốc, lần sau trả file đã lưu.
        Storage::disk('media')->assertExists(ShareImage::pathFor($group->cover_path));
        $this->get($url)->assertOk();
    }

    public function test_anh_share_nhom_sai_ten_hoac_chua_co_bia_thi_404(): void
    {
        $group = $this->makeGroup();
        $this->assertNull(MediaUrl::groupShare($group));
        $this->get("/media/{$group->code}/brand/og/abc.jpg")->assertNotFound();

        $this->uploadGroupCover($group, UploadedFile::fake()->image('c.jpg', 1600, 533));
        $url = MediaUrl::groupShare($group->refresh());
        $this->get(preg_replace('#/[^/]+\.jpg$#', '/doan-mo.jpg', $url))->assertNotFound();
        $this->get(str_replace('.jpg', '', $url))->assertNotFound();
    }

    public function test_doi_hoac_go_anh_bia_thi_xoa_anh_share_cu(): void
    {
        $group = $this->makeGroup();
        $this->uploadGroupCover($group, UploadedFile::fake()->image('a.jpg', 1600, 533));
        $first = $group->refresh()->cover_path;
        $firstUrl = MediaUrl::groupShare($group);
        $this->get($firstUrl)->assertOk();
        Storage::disk('media')->assertExists(ShareImage::pathFor($first));

        $this->uploadGroupCover($group, UploadedFile::fake()->image('b.jpg', 1600, 533));
        $second = $group->refresh()->cover_path;
        Storage::disk('media')->assertMissing([$first, ShareImage::pathFor($first)]);
        $this->get($firstUrl)->assertNotFound();
        $this->get(MediaUrl::groupShare($group))->assertOk();

        $this->actingAs($this->ownerOf($group))->deleteJson($this->adminUrl($group, 'settings/images/cover'))->assertOk();
        Storage::disk('media')->assertMissing([$second, ShareImage::pathFor($second)]);
    }

    public function test_anh_share_ca_nhan_tu_anh_dai_dien_vuong(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/api/me/images', ['kind' => 'avatar', 'image' => UploadedFile::fake()->image('a.jpg', 800, 800)], ['Accept' => 'application/json'])
            ->assertOk();
        auth()->logout();
        $user->refresh();

        $url = MediaUrl::profileShare($user, 'avatar');
        $this->assertSame("/media/u/{$user->id}/og/avatar/".pathinfo($user->avatar_path, PATHINFO_FILENAME).'.jpg', $url);
        $jpeg = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->getFile()->getContent();
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($jpeg), 0, 2));

        $this->assertNull(MediaUrl::profileShare($user, 'cover'));
        $this->get(str_replace('/og/avatar/', '/og/cover/', $url))->assertNotFound();
        $this->get('/media/u/999999/og/avatar/abc.jpg')->assertNotFound();

        $avatar = $user->avatar_path;
        Storage::disk('media')->assertExists(ShareImage::pathFor($avatar));
        $this->actingAs($user)->deleteJson('/api/me/images/avatar')->assertOk();
        Storage::disk('media')->assertMissing([$avatar, ShareImage::pathFor($avatar)]);
    }
}
