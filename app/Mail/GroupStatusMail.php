<?php

namespace App\Mail;

use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Báo nhóm bị khóa / mở khóa bởi quản trị Quỹ nhóm (plan/03 mục 8). */
class GroupStatusMail extends Mailable
{
    use Queueable;

    public function __construct(public Group $group, public bool $locked, public ?string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->locked
            ? "[Quỹ nhóm] Nhóm {$this->group->name} đang tạm khóa"
            : "[Quỹ nhóm] Nhóm {$this->group->name} đã được mở khóa");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.group-status', with: ['url' => url("/{$this->group->code}")]);
    }
}
