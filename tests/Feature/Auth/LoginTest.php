<?php

namespace Tests\Feature\Auth;

use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    public function test_dang_nhap_bang_sdt_moi_dinh_dang(): void
    {
        $user = User::factory()->create(['phone' => '0988777222', 'password' => 'matkhau123']);
        $this->makeGroup(['code' => 'nhomabc'], $user);

        $this->postJson('/api/login', ['phone' => '+84 988.777.222', 'password' => 'matkhau123'])
            ->assertOk()
            ->assertJsonPath('data.groups.0.code', 'nhomabc');

        $this->assertAuthenticatedAs($user);
    }

    public function test_sai_mat_khau_va_bi_chan_sau_5_lan(): void
    {
        User::factory()->create(['phone' => '0988777222', 'password' => 'matkhau123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['phone' => '0988777222', 'password' => 'sai'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['phone' => '0988777222', 'password' => 'matkhau123'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_me_khi_chua_dang_nhap_tra_null(): void
    {
        $this->getJson('/api/me')->assertOk()->assertExactJson(['data' => null]);
    }

    public function test_mat_khau_tam_bat_doi_truoc_khi_lam_viec_khac(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup([], $owner);
        $user = User::factory()->create(['password' => 'tam12345', 'must_change_password' => true]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 'admin']);

        $this->actingAs($user)->getJson($this->adminUrl($group, 'contacts'))
            ->assertForbidden()
            ->assertJson(['code' => 'must_change_password']);

        $this->putJson('/api/me/password', ['password' => 'moi123456', 'password_confirmation' => 'moi123456'])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', false);

        $this->getJson($this->adminUrl($group, 'contacts'))->assertOk();
    }

    public function test_doi_mat_khau_can_mat_khau_cu(): void
    {
        $user = User::factory()->create(['password' => 'matkhau123']);

        $this->actingAs($user)->putJson('/api/me/password', ['current_password' => 'sai', 'password' => 'moi123456', 'password_confirmation' => 'moi123456'])
            ->assertJsonValidationErrors('current_password');
        $this->putJson('/api/me/password', ['current_password' => 'matkhau123', 'password' => 'moi123456', 'password_confirmation' => 'moi123456'])
            ->assertOk();
    }

    public function test_quen_mat_khau_gui_email_neu_co_va_dat_lai_duoc(): void
    {
        Notification::fake();
        $user = User::factory()->create(['phone' => '0988777222', 'email' => 'an@example.com']);
        $noEmail = User::factory()->create(['phone' => '0912345678', 'email' => null]);

        $message = $this->postJson('/api/forgot-password', ['phone' => '0988777222'])->assertOk()->json('message');
        $this->assertSame($message, $this->postJson('/api/forgot-password', ['phone' => '0912345678'])->json('message'));
        $this->assertSame($message, $this->postJson('/api/forgot-password', ['phone' => '0999999999'])->json('message'));

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        Notification::assertNotSentTo($noEmail, ResetPassword::class);

        $this->postJson('/api/reset-password', ['token' => $token, 'email' => 'an@example.com', 'password' => 'moi123456', 'password_confirmation' => 'moi123456'])
            ->assertOk();
        $this->postJson('/api/login', ['phone' => '0988777222', 'password' => 'moi123456'])->assertOk();
    }

    public function test_tao_them_nhom_va_gioi_han_so_nhom(): void
    {
        $user = User::factory()->create();
        config(['cashflow.limits.groups_per_user' => 2]);

        $this->actingAs($user)->postJson('/api/groups', ['name' => 'Nhóm 1', 'code' => 'nhom-mot', 'visibility' => 'public'])->assertCreated();
        $this->postJson('/api/groups', ['name' => 'Nhóm 2', 'code' => 'nhom-hai', 'visibility' => 'public'])->assertCreated();
        $this->postJson('/api/groups', ['name' => 'Nhóm 3', 'code' => 'nhom-ba', 'visibility' => 'public'])->assertJsonValidationErrors('name');

        $this->getJson('/api/me')->assertJsonCount(2, 'data.groups');
    }
}
