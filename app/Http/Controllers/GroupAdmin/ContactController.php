<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\CampaignMember;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Transaction;
use App\Services\Contacts\ContactImportParser;
use App\Support\PhoneHash;
use App\Support\VietnameseText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Danh bạ thành viên và nhập nhanh hàng loạt, xem plan/02 mục 4. */
class ContactController extends Controller
{
    public function index(Request $request, Group $group)
    {
        $status = $request->query('status', 'active');
        $search = VietnameseText::fold($request->query('search'));

        $members = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('contact_id');

        return Contact::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status === 'left' ? 'left' : 'active'))
            ->when($search !== '', fn ($q) => $q->where('search_name', 'like', "%{$search}%"))
            ->orderBy('sort_order')->orderBy('search_name')
            ->get()
            ->map(fn (Contact $c) => $this->present($c, $members->get($c->id)));
    }

    public function store(Request $request, Group $group)
    {
        $data = $this->validated($request);
        $this->ensureUniquePhone($data['phone'] ?? null);

        $contact = Contact::create($data + ['sort_order' => (int) Contact::max('sort_order') + 1]);

        return response()->json($this->present($contact, null), 201);
    }

    public function update(Request $request, Group $group, Contact $contact)
    {
        $data = $this->validated($request);
        $data['status'] = $request->input('status', $contact->status) === 'left' ? 'left' : 'active';
        $this->ensureUniquePhone($data['phone'] ?? null, $contact);

        $contact->update($data);

        $member = GroupMember::where('group_id', $group->id)->where('contact_id', $contact->id)->where('status', 'active')->first();

        return $this->present($contact, $member);
    }

    public function destroy(Group $group, Contact $contact)
    {
        $inUse = $contact->user_id !== null
            || Transaction::withTrashed()->where('contact_id', $contact->id)->exists()
            || CampaignMember::where('contact_id', $contact->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'contact' => 'Người này đã có tài khoản hoặc lịch sử thu chi. Đánh dấu "Đã rời nhóm" thay vì xóa.',
            ]);
        }

        $contact->delete();

        return response()->noContent();
    }

    public function importPreview(Request $request, Group $group, ContactImportParser $parser)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:200000']]);

        return ['rows' => $parser->parse($data['text'], $group->id)];
    }

    /**
     * Nhập từ file Excel theo mẫu (plan/02 mục 4): đọc trang đầu tiên, đổi thành
     * các dòng cách tab rồi dùng chung bộ đọc với ô dán, ra màn hình xem trước.
     */
    public function importFile(Request $request, Group $group, ContactImportParser $parser)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']], [], ['file' => 'file danh bạ']);

        try {
            $sheet = IOFactory::load($request->file('file')->getRealPath())->getSheet(0);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Không đọc được file. Hãy dùng file Excel (.xlsx) theo mẫu.']);
        }

        $lines = [];
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $cells = array_map(function ($v) {
                if (is_float($v) && floor($v) === $v) {
                    $v = (int) $v; // SĐT hoặc ngày dạng số sê-ri Excel
                }

                return str_replace(["\t", "\r", "\n"], ' ', trim((string) $v));
            }, array_slice($row, 0, 4));
            if (implode('', $cells) !== '') {
                $lines[] = implode("\t", $cells);
            }
            if (count($lines) > ContactImportParser::MAX_ROWS + 1) {
                break;
            }
        }

        return ['rows' => $parser->parse(implode("\n", $lines), $group->id)];
    }

    /** File Excel mẫu: Họ tên, SĐT (định dạng chữ để giữ số 0), Ngày sinh, Địa chỉ. */
    public function importTemplate()
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Danh bạ');
        $sheet->fromArray([['Họ tên', 'SĐT', 'Ngày sinh', 'Địa chỉ'], ['Nguyễn Thị Lan', '0912345678', '12/03/1995', 'Hà Nội']], null, 'A1');
        $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getCell('B2')->setValueExplicit('0912345678', DataType::TYPE_STRING);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (['A' => 28, 'B' => 16, 'C' => 14, 'D' => 36] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'wepo-mau-danh-ba.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request, Group $group, ContactImportParser $parser)
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'max:'.ContactImportParser::MAX_ROWS],
            'rows.*.full_name' => ['nullable', 'string'],
            'rows.*.phone' => ['nullable', 'string'],
            'rows.*.birthday' => ['nullable', 'string'],
            'rows.*.address' => ['nullable', 'string'],
        ]);

        $rows = $parser->validateRows($data['rows'], $group->id);
        $invalid = array_values(array_filter($rows, fn ($r) => $r['errors'] !== []));

        if ($invalid !== []) {
            return response()->json([
                'message' => 'Còn '.count($invalid).' dòng lỗi, sửa hoặc bỏ các dòng đó rồi lưu lại.',
                'rows' => $rows,
            ], 422);
        }

        $order = (int) Contact::max('sort_order');
        DB::connection('groups')->transaction(function () use ($rows, &$order) {
            foreach ($rows as $row) {
                Contact::create([
                    'full_name' => $row['full_name'],
                    'phone' => $row['phone'],
                    'birthday' => $row['birthday'],
                    'address' => $row['address'],
                    'sort_order' => ++$order,
                ]);
            }
        });

        return response()->json(['created' => count($rows)], 201);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function ensureUniquePhone(?string $phone, ?Contact $ignore = null): void
    {
        if ($phone === null || trim($phone) === '') {
            return;
        }

        $hash = PhoneHash::make($phone)
            ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);

        $other = Contact::where('phone_hash', $hash)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();

        if ($other) {
            throw ValidationException::withMessages(['phone' => "Số điện thoại này đã có trong danh bạ ({$other->full_name})."]);
        }
    }

    private function present(Contact $c, ?GroupMember $member): array
    {
        return [
            'id' => $c->id,
            'full_name' => $c->full_name,
            'phone' => $c->phone,
            'birthday' => $c->birthday?->toDateString(),
            'address' => $c->address,
            'note' => $c->note,
            'status' => $c->status,
            'account' => $member === null ? null : [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
            ],
        ];
    }
}
