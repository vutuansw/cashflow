<?php

namespace App\Providers;

use App\Services\Ai\BillExtractor;
use App\Services\Ai\FakeBillExtractor;
use App\Services\Ai\OpenRouterBillExtractor;
use App\Support\CurrentGroup;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mỗi request một nhóm hiện tại; scoped để không rò sang request sau nếu chạy Octane/queue.
        $this->app->scoped(CurrentGroup::class);

        // AI đọc bill: OpenRouter thật, hoặc bản giả cho test và máy dev chưa có khóa (plan/04 mục 5).
        $this->app->bind(BillExtractor::class, fn () => config('ai.driver') === 'fake'
            ? new FakeBillExtractor
            : new OpenRouterBillExtractor);
    }

    public function boot(): void
    {
        // Bắt sớm lỗi gán thuộc tính sai tên và truy cập quan hệ chưa nạp (N+1) khi dev.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Đăng ký tài khoản (/register và link xin vào nhóm), xem plan/02 mục 2 và plan/18:
        // chỉ đếm lần tạo được tài khoản (10/giờ/IP, cả nhóm đăng ký chung một Wi-Fi vẫn đủ). Gõ sai rồi sửa (422)
        // không mất lượt; riêng tổng số lần gửi vẫn chặn ở 30/giờ/IP để chống spam.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perHour(10)->by('created:'.$request->ip())->after(fn ($response) => $response->getStatusCode() === 201),
            Limit::perHour(30)->by('tries:'.$request->ip()),
        ]);

        // Webhook SePay: 120 request/phút mỗi nhóm, xem plan/05 mục 3.
        RateLimiter::for('sepay', fn (Request $request) => Limit::perMinute(120)->by('sepay:'.$request->route('group')));

        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/reset-password').'?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]));

        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Đặt lại mật khẩu WePo')
            ->greeting("Chào {$user->name},")
            ->line('Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu tài khoản WePo.')
            ->action('Đặt lại mật khẩu', url('/reset-password').'?'.http_build_query(['token' => $token, 'email' => $user->email]))
            ->line('Link có hiệu lực trong 60 phút. Nếu bạn không yêu cầu, cứ bỏ qua email này.')
            ->salutation('WePo'));
    }
}
