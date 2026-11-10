<?php

namespace Database\Seeders;

use App\Enums\GroupRole;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Reaction;
use App\Models\System\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Groups\GroupCreator;
use App\Services\Transactions\TransactionWriter;
use App\Support\CurrentGroup;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Dữ liệu mẫu để chạy thử và demo, xem plan/07 mục 3:
 * - nhomdemo: nhóm công khai, xem được khi chưa đăng nhập;
 * - chidoan-a12: nhóm riêng tư, phải đăng nhập.
 *
 * Tài khoản mẫu (mật khẩu đều là "demo1234"):
 * - 0900000001 Trưởng nhóm của cả hai nhóm
 * - 0900000002 Quản trị viên chidoan-a12
 * - 0900000003 Thành viên chidoan-a12
 * - admin@quynhom.test: quản trị /system (cùng mật khẩu). Server thật tạo bằng
 *   php artisan cashflow:admin-create {email}
 *
 * Chạy lại an toàn: nhóm nào đã có mã thì bỏ qua.
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'demo1234';

    private const NAMES = [
        'Nguyễn Thị Lan', 'Trần Văn Minh', 'Lê Hoàng Nam', 'Phạm Thu Hà', 'Hoàng Đức Anh', 'Vũ Thị Mai',
        'Đặng Quốc Bảo', 'Bùi Ngọc Ánh', 'Đỗ Minh Tuấn', 'Ngô Thanh Hương', 'Dương Văn Hùng', 'Lý Thị Hoa',
        'Phan Anh Dũng', 'Trịnh Thu Trang', 'Đinh Công Sơn', 'Mai Phương Thảo', 'Tô Văn Long', 'Hồ Thị Ngọc',
        'Lương Minh Khoa', 'Chu Thị Yến', 'Cao Văn Thành', 'Kiều Thị Linh', 'Tạ Quang Huy', 'Lâm Thị Diệu',
    ];

    private ImageManager $images;

    public function __construct(private readonly TransactionWriter $writer, private readonly GroupCreator $creator)
    {
        $this->images = new ImageManager(new Driver);
    }

    public function run(): void
    {
        $owner = $this->user('0900000001', 'Vũ Tuấn', 'demo-truongnhom@example.test');
        $admin = $this->user('0900000002', 'Trần Văn Minh', null);
        $member = $this->user('0900000003', 'Nguyễn Thị Lan', null);

        $this->group($owner, [
            'code' => 'nhomdemo',
            'name' => 'Nhóm bạn thân Demo',
            'visibility' => 'public',
            'opening_balance' => 3_200_000,
            'description' => 'Nhóm mẫu công khai: ai có link đều xem được thu chi.',
        ], [], seedOffset: 0);

        $this->group($owner, [
            'code' => 'chidoan-a12',
            'name' => 'Chi đoàn A12',
            'visibility' => 'private',
            'opening_balance' => 2_500_000,
            'description' => 'Quỹ lớp A12 khóa 2010–2013.',
        ], [[$admin, GroupRole::Admin], [$member, GroupRole::Member]], seedOffset: 7);

        Admin::query()->firstOrCreate(['email' => 'admin@quynhom.test'], ['name' => 'Quản trị demo', 'password' => self::PASSWORD, 'status' => 'active']);

        $this->command?->info('Xong. Đăng nhập 0900000001 / '.self::PASSWORD.' để xem; /nhomdemo xem được khi chưa đăng nhập.');
    }

    private function user(string $phone, string $name, ?string $email): User
    {
        return User::query()->wherePhone($phone)->first()
            ?? User::create(['name' => $name, 'phone' => $phone, 'email' => $email, 'password' => self::PASSWORD]);
    }

    /** @param  list<array{User, GroupRole}>  $accounts */
    private function group(User $owner, array $data, array $accounts, int $seedOffset): void
    {
        if (Group::withTrashed()->where('code', $data['code'])->exists()) {
            $this->command?->warn("Nhóm {$data['code']} đã có, bỏ qua.");

            return;
        }

        mt_srand(2026 + $seedOffset);
        $group = $this->creator->create($owner, $data);
        app(CurrentGroup::class)->set($group);

        // Danh bạ: 24 người, một số đã có tài khoản.
        $contacts = collect(self::NAMES)->map(fn ($name, $i) => Contact::create([
            'full_name' => $name,
            'phone' => '0912'.str_pad((string) ($seedOffset * 100 + $i), 6, '0', STR_PAD_LEFT),
            'birthday' => Carbon::create(1995, 1, 1)->addDays(mt_rand(0, 900))->toDateString(),
            'sort_order' => $i + 1,
        ]));

        foreach ($accounts as [$user, $role]) {
            $contact = $contacts->firstWhere('full_name', $user->name);
            $contact?->update(['user_id' => $user->id]);
            GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role, 'contact_id' => $contact?->id]);
        }

        $category = fn (string $name) => Category::where('name', $name)->firstOrFail();

        // Chiến dịch thu quỹ năm nay; khoảng 2/3 người đã đóng, rải trong vài ngày.
        $fund = $category('Đóng quỹ năm');
        $campaign = Campaign::create([
            'category_id' => $fund->id,
            'name' => 'Thu quỹ năm '.now()->year,
            'slug' => 'thu-quy-'.now()->year,
            'transfer_code' => strtoupper(substr(preg_replace('/[^a-z0-9]/', '', $group->code), 0, 6)).'N'.substr((string) now()->year, 2),
            'amount_per_member' => 500_000,
            'starts_on' => now()->subDays(20)->toDateString(),
            'status' => 'open',
            'created_by' => $owner->id,
        ]);
        foreach ($contacts as $contact) {
            CampaignMember::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'expected_amount' => 500_000]);
        }

        foreach ($contacts->take(16) as $i => $contact) {
            $this->writer->create($group, $owner, [
                'type' => 'income',
                'category_id' => $fund->id,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'amount' => $i === 5 ? 300_000 : 500_000,
                'occurred_at' => now()->subDays(intdiv($i, 4) * 3 + 1)->setTime(8 + $i % 4, 15)->toDateTimeString(),
            ]);
        }

        // Các khoản chi có ảnh sự kiện, rải trong 14 tháng gần đây.
        $expenses = [
            ['Thăm ốm', 'Thăm ốm bạn Lan', 500_000, 12, 'Cả nhóm vào viện thăm Lan, chúc bạn mau khỏe.', 2, '#0ea5e9'],
            ['Liên hoan', 'Liên hoan cuối năm', 2_450_000, 40, 'Lẩu nướng ở quán quen, 18 người tham gia.', 3, '#f59e0b'],
            ['Đám hỉ', 'Mừng cưới bạn Hà', 1_000_000, 75, null, 1, '#ec4899'],
            ['Thăm đẻ', 'Mừng em bé nhà Tuấn', 500_000, 120, 'Chúc mẹ tròn con vuông.', 1, '#a855f7'],
            ['Đám hiếu', 'Viếng bác nhà bạn Nam', 1_000_000, 160, null, 0, '#64748b'],
            ['Liên hoan', 'Gặp mặt đầu năm', 1_800_000, 300, 'Họp lớp đầu xuân.', 2, '#22c55e'],
            ['Thăm ốm', 'Thăm ốm bạn Hùng', 300_000, 380, null, 0, '#0ea5e9'],
        ];
        $posts = [];
        foreach ($expenses as [$cat, $title, $amount, $daysAgo, $description, $photos, $color]) {
            $posts[] = $this->writer->create($group, $owner, [
                'type' => 'expense',
                'category_id' => $category($cat)->id,
                'amount' => $amount,
                'occurred_at' => now()->subDays($daysAgo)->setTime(18, 30)->toDateTimeString(),
                'title' => $title,
                'description' => $description,
            ], $this->photos($photos, $color));
        }

        // Năm trước cũng có thu quỹ để biểu đồ và so sánh cùng kỳ có số.
        foreach ($contacts->take(20) as $i => $contact) {
            $this->writer->create($group, $owner, [
                'type' => 'income',
                'category_id' => $fund->id,
                'contact_id' => $contact->id,
                'amount' => 400_000,
                'occurred_at' => now()->subYear()->startOfYear()->addDays(10 + $i * 9)->setTime(9, 0)->toDateTimeString(),
                'show_on_timeline' => $i < 3,
            ]);
        }

        // Tim và bình luận từ các tài khoản của nhóm.
        $users = collect([$owner, ...array_column($accounts, 0)]);
        foreach (array_slice($posts, 0, 3) as $post) {
            foreach ($users as $user) {
                Reaction::firstOrCreate(['transaction_id' => $post->id, 'user_id' => $user->id]);
            }
            Comment::create(['transaction_id' => $post->id, 'user_id' => $users->last()->id, 'body' => 'Cảm ơn cả nhóm nhiều!']);
        }
        Comment::create(['transaction_id' => $posts[1]->id, 'user_id' => $owner->id, 'body' => 'Hóa đơn chi tiết mình để trong phiếu nhé.']);

        $this->command?->info("Đã tạo nhóm {$group->code}: ".Transaction::count().' phiếu.');
    }

    /** Ảnh minh họa đơn giản (nền màu và vài hình tròn), đủ để thử lưới ảnh và trang xem ảnh. */
    private function photos(int $count, string $color): array
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $image = $this->images->create(1200, $i % 2 === 0 ? 900 : 1500)->fill($color);
            for ($c = 0; $c < 6; $c++) {
                $image->drawCircle(mt_rand(0, 1200), mt_rand(0, 1500), function ($circle) {
                    $circle->radius(mt_rand(60, 260));
                    $circle->background('rgba(255, 255, 255, 0.25)');
                });
            }
            $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
            $image->toJpeg(85)->save($path);
            $files[] = new UploadedFile($path, "demo-{$i}.jpg", 'image/jpeg', null, true);
        }

        return $files;
    }
}
