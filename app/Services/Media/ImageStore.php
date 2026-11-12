<?php

namespace App\Services\Media;

use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Lưu ảnh tải lên vào đĩa riêng tư `media`, thư mục groups/{group_id}/...
 *
 * Mọi ảnh đều được xoay theo EXIF, thu nhỏ và mã hóa lại sang webp. Mã hóa lại
 * bằng GD không giữ EXIF nên vị trí GPS trong ảnh điện thoại bị bỏ. Xem plan/03
 * mục 3.
 */
class ImageStore
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver, autoOrientation: true, strip: true);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk('media');
    }

    /**
     * Ảnh sự kiện của phiếu (thư mục photos) hoặc ảnh bài đăng thành viên (thư mục posts, plan/21):
     * bản lớn 1600px và thumbnail 480px.
     *
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function storeEventPhoto(Group $group, UploadedFile $file, string $folder = 'photos'): array
    {
        $image = $this->read($file);
        $base = $this->basePath($group, $folder);

        $large = $image->scaleDown(config('cashflow.images.large'), config('cashflow.images.large'));
        $this->put("{$base}.webp", $large);
        $width = $large->width();
        $height = $large->height();

        $thumb = $large->scaleDown(config('cashflow.images.thumb'), config('cashflow.images.thumb'));
        $this->put("{$base}_sm.webp", $thumb);

        return ['path' => "{$base}.webp", 'thumb_path' => "{$base}_sm.webp", 'width' => $width, 'height' => $height];
    }

    /** Ảnh bill chứng từ: chỉ giữ một bản 1600px (đủ nét để AI và người đọc). */
    public function storeBill(Group $group, UploadedFile $file): string
    {
        $image = $this->read($file)->scaleDown(config('cashflow.images.large'), config('cashflow.images.large'));
        $path = $this->basePath($group, 'bills').'.webp';
        $this->put($path, $image);

        return $path;
    }

    /** Ảnh bìa nhóm, tỉ lệ 3:1. Nhóm không có ảnh đại diện (plan/08 mục 10d). */
    public function storeGroupCover(Group $group, UploadedFile $file): string
    {
        $path = $this->basePath($group, 'brand', 'cover').'.webp';
        $this->put($path, $this->cropForKind($this->read($file), 'cover'));

        return $path;
    }

    /**
     * Ảnh đại diện hoặc ảnh bìa trang cá nhân. Tài khoản dùng chung nhiều nhóm
     * nên lưu trong users/{id}, không nằm trong thư mục nhóm. Xem plan/08 mục 4.
     */
    public function storeProfileImage(User $user, UploadedFile $file, string $kind): string
    {
        $path = "users/{$user->id}/profile/".now()->format('Y/m')."/{$kind}-".Str::lower(Str::random(24)).'.webp';
        $this->put($path, $this->cropForKind($this->read($file), $kind));

        return $path;
    }

    /**
     * Xóa ảnh, kèm ảnh share `_og.jpg` của nó nếu đã từng tạo (plan/29).
     *
     * @param  iterable<?string>  $paths
     */
    public function delete(iterable $paths): void
    {
        $paths = array_values(array_filter(is_array($paths) ? $paths : iterator_to_array($paths)));
        if ($paths !== []) {
            self::disk()->delete([...$paths, ...array_map(ShareImage::pathFor(...), $paths)]);
        }
    }

    private function read(UploadedFile $file): ImageInterface
    {
        try {
            return $this->manager->read($file->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'photos' => 'Không đọc được ảnh "'.$file->getClientOriginalName().'". Hãy chọn ảnh JPG, PNG hoặc WebP.',
            ]);
        }
    }

    /** Ảnh đại diện cắt vuông ở giữa; ảnh bìa cắt tỉ lệ 3:1 ở giữa. */
    private function cropForKind(ImageInterface $image, string $kind): ImageInterface
    {
        $avatar = config('cashflow.images.avatar');
        $cover = config('cashflow.images.cover');

        return $kind === 'avatar'
            ? $image->cover($avatar, $avatar)
            : $image->cover($cover, (int) round($cover / 3));
    }

    private function put(string $path, ImageInterface $image): void
    {
        self::disk()->put($path, (string) $image->toWebp(config('cashflow.images.quality')));
    }

    /** Tên file ngẫu nhiên: không đoán được và đổi theo mỗi lần tải nên cache lâu được. */
    private function basePath(Group $group, string $folder, string $prefix = ''): string
    {
        $name = ($prefix === '' ? '' : $prefix.'-').Str::lower(Str::random(24));

        return "groups/{$group->id}/{$folder}/".now()->format('Y/m')."/{$name}";
    }
}
