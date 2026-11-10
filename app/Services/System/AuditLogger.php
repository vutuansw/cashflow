<?php

namespace App\Services\System;

use App\Models\System\AdminAuditLog;
use Illuminate\Support\Facades\Auth;

/** Ghi admin_audit_logs cho mọi thao tác của quản trị /system (plan/01 mục 2). */
class AuditLogger
{
    public function log(string $action, ?int $groupId = null, array $payload = []): void
    {
        AdminAuditLog::query()->forceCreate([
            'admin_id' => Auth::guard('system')->id(),
            'action' => $action,
            'group_id' => $groupId,
            'payload' => $payload ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
