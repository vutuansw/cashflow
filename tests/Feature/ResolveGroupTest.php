<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Group;
use App\Models\System\GroupService;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class ResolveGroupTest extends TestCase
{
    use RefreshBothDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'group'])->get('/api/_test/{group}/contacts', fn (Group $group) => [
            'group' => $group->code,
            'contacts' => Contact::pluck('full_name'),
        ]);
    }

    public function test_dat_nhom_hien_tai_tu_ma_nhom(): void
    {
        $a = Group::factory()->create(['code' => 'chidoan-a12']);
        $b = Group::factory()->create(['code' => 'nhomabc']);
        Contact::factory()->for($a)->create(['full_name' => 'Người nhóm A']);
        Contact::factory()->for($b)->create(['full_name' => 'Người nhóm B']);

        $this->getJson('/api/_test/chidoan-a12/contacts')
            ->assertOk()
            ->assertExactJson(['group' => 'chidoan-a12', 'contacts' => ['Người nhóm A']]);
    }

    public function test_ma_nhom_khong_ton_tai_hoac_da_xoa_tra_404(): void
    {
        $this->getJson('/api/_test/khong-co/contacts')->assertNotFound();

        Group::factory()->create(['code' => 'da-xoa'])->delete();
        $this->getJson('/api/_test/da-xoa/contacts')->assertNotFound();
    }

    public function test_nhom_bi_khoa_tra_423(): void
    {
        $group = Group::factory()->create(['code' => 'bi-khoa']);
        GroupService::create(['group_id' => $group->id, 'service_status' => 'locked', 'lock_reason' => 'Vi phạm']);

        $this->getJson('/api/_test/bi-khoa/contacts')->assertStatus(423);
    }
}
