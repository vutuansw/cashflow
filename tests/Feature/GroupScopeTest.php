<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Group;
use App\Support\CurrentGroup;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

/** Nguyên tắc bất biến số 1: mọi truy vấn dữ liệu nhóm đều lọc theo group_id. */
class GroupScopeTest extends TestCase
{
    use RefreshBothDatabases;

    public function test_chi_thay_du_lieu_cua_nhom_hien_tai(): void
    {
        [$a, $b] = Group::factory()->count(2)->create();
        $contactA = Contact::factory()->for($a)->create();
        $contactB = Contact::factory()->for($b)->create();

        app(CurrentGroup::class)->set($a);

        $this->assertSame([$contactA->id], Contact::pluck('id')->all());
        $this->assertNull(Contact::find($contactB->id));
    }

    public function test_chua_co_nhom_hien_tai_thi_khong_tra_dong_nao(): void
    {
        Contact::factory()->count(2)->create();

        $this->assertSame(0, Contact::count());
        $this->assertSame(2, Contact::acrossGroups()->count());
    }

    public function test_tao_moi_tu_dien_group_id_cua_nhom_hien_tai(): void
    {
        $group = Group::factory()->create();
        app(CurrentGroup::class)->set($group);

        $contact = Contact::create(['full_name' => 'Lê Thị Hoa']);

        $this->assertSame($group->id, $contact->group_id);
    }
}
