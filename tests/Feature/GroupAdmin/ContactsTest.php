<?php

namespace Tests\Feature\GroupAdmin;

use App\Enums\GroupRole;
use App\Models\Contact;
use App\Support\CurrentGroup;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesGroups;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use CreatesGroups, RefreshBothDatabases;

    public function test_them_sua_danh_ba_va_chan_trung_sdt(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $id = $this->postJson($this->adminUrl($group, 'contacts'), [
            'full_name' => 'Trần Thị Lan', 'phone' => '0912 345 678', 'birthday' => '1995-03-05', 'address' => 'Hà Nội',
        ])->assertCreated()->assertJsonPath('phone', '0912345678')->json('id');

        $this->postJson($this->adminUrl($group, 'contacts'), ['full_name' => 'Người khác', 'phone' => '+84912345678'])
            ->assertJsonValidationErrors('phone');

        $this->putJson($this->adminUrl($group, "contacts/{$id}"), ['full_name' => 'Trần Thị Lan', 'phone' => '0912345678', 'status' => 'left'])
            ->assertOk()->assertJsonPath('status', 'left');

        $this->getJson($this->adminUrl($group, 'contacts?search=lan'))->assertJsonCount(0);
        $this->getJson($this->adminUrl($group, 'contacts?status=left&search=lan'))->assertJsonCount(1);
    }

    public function test_nhap_hang_loat_dan_tu_excel(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));

        $lines = ["Họ tên\tSĐT\tNgày sinh\tĐịa chỉ"];
        for ($i = 1; $i <= 30; $i++) {
            // Dòng thứ 3 giả lập Excel làm mất số 0 đầu.
            $phone = $i === 3 ? '9770000'.sprintf('%02d', $i) : '09770000'.sprintf('%02d', $i);
            $lines[] = "Thành viên {$i}\t{$phone}\t".sprintf('%02d', ($i % 28) + 1)."/05/1998\tSố {$i} Lê Lợi";
        }

        $rows = $this->postJson($this->adminUrl($group, 'contacts/import/preview'), ['text' => implode("\n", $lines)])
            ->assertOk()->json('rows');

        $this->assertCount(30, $rows);
        $this->assertSame([], array_merge(...array_column($rows, 'errors')));
        $this->assertSame('0977000003', $rows[2]['phone']);
        $this->assertSame('1998-05-04', $rows[2]['birthday']);

        $this->postJson($this->adminUrl($group, 'contacts/import'), ['rows' => $rows])
            ->assertCreated()->assertJson(['created' => 30]);

        app(CurrentGroup::class)->set($group);
        $this->assertSame(31, Contact::count()); // 30 người + trưởng nhóm
        $this->assertStringNotContainsString('0977000003', (string) DB::table('contacts')->where('full_name', 'Thành viên 3')->value('phone'));
    }

    public function test_nhap_hang_loat_bao_loi_va_trung(): void
    {
        $group = $this->makeGroup();
        $this->actingAs($this->ownerOf($group));
        $ownerPhone = $this->ownerOf($group)->phone;

        $text = "An, 0988111222, 31/02/1990\nBình, 12345\n, 0988111333\nChi, 0988111222\nDũng, {$ownerPhone}\nGiang, 0988111444, , 12 Trần Phú, Hà Nội";
        $rows = $this->postJson($this->adminUrl($group, 'contacts/import/preview'), ['text' => $text])->json('rows');

        $this->assertNotEmpty($rows[0]['errors']);           // ngày 31/02
        $this->assertNotEmpty($rows[1]['errors']);           // SĐT sai
        $this->assertNotEmpty($rows[2]['errors']);           // thiếu tên
        $this->assertStringContainsString('trùng với dòng 1', $rows[3]['errors'][0]);
        $this->assertStringContainsString('đã có trong danh bạ', $rows[4]['errors'][0]);
        $this->assertSame([], $rows[5]['errors']);
        $this->assertSame('12 Trần Phú, Hà Nội', $rows[5]['address']);

        $this->postJson($this->adminUrl($group, 'contacts/import'), ['rows' => $rows])->assertUnprocessable();
    }

    public function test_thanh_vien_va_nguoi_nhom_khac_khong_vao_duoc(): void
    {
        $group = $this->makeGroup();
        $other = $this->makeGroup();
        $member = $this->memberOf($group, GroupRole::Member);

        $this->actingAs($member)->getJson($this->adminUrl($group, 'contacts'))->assertForbidden();
        $this->actingAs($this->ownerOf($other))->getJson($this->adminUrl($group, 'contacts'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($this->adminUrl($group, 'contacts'))->assertUnauthorized();
    }

    public function test_khong_sua_duoc_danh_ba_nhom_khac_qua_id(): void
    {
        $a = $this->makeGroup();
        $b = $this->makeGroup();
        $contactB = Contact::acrossGroups()->where('group_id', $b->id)->firstOrFail();

        $this->actingAs($this->ownerOf($a))
            ->putJson($this->adminUrl($a, "contacts/{$contactB->id}"), ['full_name' => 'Hack'])
            ->assertNotFound();
        $this->deleteJson($this->adminUrl($a, "contacts/{$contactB->id}"))->assertNotFound();
    }

    public function test_khong_xoa_nguoi_da_co_tai_khoan(): void
    {
        $group = $this->makeGroup();
        $owner = $this->ownerOf($group);
        $ownerContact = Contact::acrossGroups()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)->deleteJson($this->adminUrl($group, "contacts/{$ownerContact->id}"))
            ->assertJsonValidationErrors('contact');
    }
}
