<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Ảnh xem trước khi dán link vào Zalo/Messenger/Facebook (thẻ og:image), xem plan/29.
 *
 * JPEG 1200×630 (nhiều app chat không đọc WebP, ô xem trước là 1.91:1). Ảnh gốc
 * đặt nguyên ở giữa, không cắt; phần trống lấp bằng chính ảnh đó phóng to làm mờ.
 * Tạo ở lần đầu có người xin rồi lưu cạnh ảnh gốc (cover-abc.webp → cover-abc_og.jpg):
 * ảnh có từ trước cũng tự có ảnh share, đổi ảnh gốc là đổi tên nên không cần ghi đè.
 */
class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver, strip: true);
    }

    /** Đường dẫn ảnh share đi kèm một ảnh gốc trên đĩa media. */
    public static function pathFor(string $source): string
    {
        return preg_replace('/\.[a-z0-9]+$/i', '', $source).'_og.jpg';
    }

    /** Trả đường dẫn ảnh share của ảnh gốc, chưa có thì vẽ và lưu. Ảnh gốc không còn thì null. */
    public function ensure(string $source): ?string
    {
        $disk = ImageStore::disk();
        $target = self::pathFor($source);

        if ($disk->exists($target)) {
            return $target;
        }
        if (! $disk->exists($source)) {
            return null;
        }

        $disk->put($target, $this->render($disk->get($source)));

        return $target;
    }

    private function render(string $bytes): string
    {
        // Nền: thu thật nhỏ rồi phóng lại qua hai bước, mỗi bước làm mờ một ít. Làm mờ thẳng trên 1200×630
        // bằng GD rất chậm; phóng một bước từ ảnh nhỏ thì lộ ô vuông.
        $canvas = $this->manager->read($bytes)
            ->cover(80, 42)
            ->blur(2)
            ->resize(300, 158)
            ->blur(6)
            ->resize(self::WIDTH, self::HEIGHT)
            ->blur(4)
            ->brightness(-12);

        // Giữa: nguyên ảnh, thu (hoặc phóng) cho vừa khung, không cắt. Bìa 1600×533 → 1200×400, đại diện 400×400 → 630×630.
        $front = $this->manager->read($bytes)->scale(self::WIDTH, self::HEIGHT);

        return (string) $canvas->place($front, 'center')->toJpeg(85, progressive: true);
    }
}
