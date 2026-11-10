<x-mail::message>
@if ($locked)
# Nhóm {{ $group->name }} đang tạm khóa

Quản trị Quỹ nhóm đã tạm khóa nhóm **{{ $group->name }}** ({{ $group->code }}). Trong thời gian khóa, trang nhóm và việc tự nhận giao dịch SePay tạm dừng; dữ liệu của nhóm vẫn được giữ nguyên.

@if ($reason)
**Lý do:** {{ $reason }}
@endif

Nếu cần hỗ trợ, bạn trả lời email này.
@else
# Nhóm {{ $group->name }} đã hoạt động lại

Nhóm **{{ $group->name }}** đã được mở khóa, mọi chức năng hoạt động bình thường.

<x-mail::button :url="$url">
Mở trang nhóm
</x-mail::button>
@endif
</x-mail::message>
