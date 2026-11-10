<?php

namespace App\Mail;

use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class SepayDigestMail extends Mailable
{
    use Queueable;

    public function __construct(public Group $group, public Collection $items, public ?Collection $claims = null)
    {
        $this->claims ??= collect();
    }

    public function envelope(): Envelope
    {
        $parts = array_filter([
            $this->items->count() ? "{$this->items->count()} giao dịch" : null,
            $this->claims->count() ? "{$this->claims->count()} bill thành viên" : null,
        ]);

        return new Envelope(subject: "[{$this->group->name}] ".implode(', ', $parts).' mới chờ duyệt');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.sepay-digest', with: [
            'url' => url("/{$this->group->code}/admin/sepay-queue"),
        ]);
    }
}
