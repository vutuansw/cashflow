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

    public function __construct(public Group $group, public Collection $items) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->group->name}] {$this->items->count()} giao dịch mới chờ duyệt");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.sepay-digest', with: [
            'url' => url("/{$this->group->code}/quan-tri/cho-duyet"),
        ]);
    }
}
