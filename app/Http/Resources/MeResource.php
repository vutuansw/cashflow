<?php

namespace App\Http\Resources;

use App\Enums\GroupRole;
use App\Models\User;
use App\Services\GroupServiceStatus;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = app(GroupServiceStatus::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'bio' => $this->bio,
            'avatar_url' => MediaUrl::profile($this->resource, 'avatar'),
            'cover_url' => MediaUrl::profile($this->resource, 'cover'),
            'must_change_password' => $this->must_change_password,
            'groups' => $this->groups()->orderBy('name')->get()->map(fn ($group) => [
                'code' => $group->code,
                'name' => $group->name,
                'visibility' => $group->visibility,
                'role' => $group->pivot->role,
                'role_label' => GroupRole::from($group->pivot->role)->label(),
                'locked' => $status->isLocked($group),
            ])->values(),
        ];
    }
}
