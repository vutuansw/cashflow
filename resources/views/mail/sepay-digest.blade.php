<x-mail::message>
# {{ $items->count() }} giao dịch mới chờ duyệt

Tài khoản nhận quỹ của **{{ $group->name }}** vừa có biến động. Các giao dịch chỉ được tính vào quỹ sau khi quản trị viên duyệt.

<x-mail::table>
| Thời gian | Số tiền | Nội dung |
| :-- | --: | :-- |
@foreach ($items as $item)
| {{ $item->transaction_date->format('d/m H:i') }} | {{ $item->transfer_type === 'in' ? '+' : '−' }}{{ number_format($item->amount, 0, ',', '.') }} đ | {{ \Illuminate\Support\Str::limit((string) $item->content, 60) }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$url">
Mở hàng chờ duyệt
</x-mail::button>

Bạn tắt được email này trong Cài đặt nhóm.
</x-mail::message>
