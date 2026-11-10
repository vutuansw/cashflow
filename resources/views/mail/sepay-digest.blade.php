<x-mail::message>
# Có khoản mới chờ duyệt

@if ($items->isNotEmpty())
Tài khoản nhận quỹ của **{{ $group->name }}** vừa có {{ $items->count() }} biến động. Các giao dịch chỉ được tính vào quỹ sau khi quản trị viên duyệt.

<x-mail::table>
| Thời gian | Số tiền | Nội dung |
| :-- | --: | :-- |
@foreach ($items as $item)
| {{ $item->transaction_date->format('d/m H:i') }} | {{ $item->transfer_type === 'in' ? '+' : '−' }}{{ number_format($item->amount, 0, ',', '.') }} đ | {{ \Illuminate\Support\Str::limit((string) $item->content, 60) }} |
@endforeach
</x-mail::table>
@endif

@if ($claims->isNotEmpty())
{{ $claims->count() }} thành viên vừa gửi bill đã chuyển khoản, chờ bạn xác nhận.

<x-mail::table>
| Người gửi | Số tiền | Chiến dịch |
| :-- | --: | :-- |
@foreach ($claims as $claim)
| {{ \Illuminate\Support\Str::limit($claim->personName(), 40) }} | {{ number_format($claim->amount, 0, ',', '.') }} đ | {{ \Illuminate\Support\Str::limit((string) $claim->campaign?->name, 40) }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$url">
Mở hàng chờ duyệt
</x-mail::button>

Bạn tắt được email này trong Cài đặt nhóm.
</x-mail::message>
