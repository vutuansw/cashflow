<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Group;
use App\Models\User;
use App\Support\CurrentGroup;
use App\Support\PhoneHash;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\RefreshBothDatabases;
use Tests\TestCase;

class PhoneEncryptionTest extends TestCase
{
    use RefreshBothDatabases;

    public function test_sdt_tai_khoan_luu_ma_hoa_va_tra_duoc_theo_hash(): void
    {
        $user = User::factory()->create(['phone' => '0988.777.222']);

        $raw = DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString('0988777222', $raw->phone);
        $this->assertSame(PhoneHash::make('0988777222'), $raw->phone_hash);

        $this->assertSame('0988777222', $user->fresh()->phone);
        $this->assertTrue(User::wherePhone('+84 988 777 222')->first()->is($user));
    }

    public function test_sdt_va_dia_chi_danh_ba_luu_ma_hoa(): void
    {
        $group = Group::factory()->create();
        app(CurrentGroup::class)->set($group);

        $contact = Contact::create(['full_name' => 'Trần Văn Đức', 'phone' => '0912345678', 'address' => '12 Lý Thường Kiệt']);

        $raw = DB::table('contacts')->where('id', $contact->id)->first();
        $this->assertStringNotContainsString('0912345678', $raw->phone);
        $this->assertStringNotContainsString('Thường Kiệt', $raw->address);
        $this->assertSame('tran van duc', $raw->search_name);
        $this->assertSame('12 Lý Thường Kiệt', $contact->fresh()->address);
    }

    public function test_sdt_khong_hop_le_bi_tu_choi(): void
    {
        $this->expectException(ValidationException::class);

        User::factory()->create(['phone' => '12345']);
    }

    public function test_hai_tai_khoan_khong_trung_sdt(): void
    {
        User::factory()->create(['phone' => '0988777222']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['phone' => '+84988777222']);
    }
}
