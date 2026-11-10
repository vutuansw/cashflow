<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Services\Media\ImageStore;
use Illuminate\Http\Request;

/**
 * Trang cá nhân của tôi: sửa thông tin, ảnh đại diện, ảnh bìa. Chỉ chính chủ
 * sửa được, QTV không sửa hộ. Xem plan/08 mục 3-4.
 */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'bio' => ['nullable', 'string', 'max:160'],
        ]);

        $user->update($data);

        return new MeResource($user);
    }

    public function uploadImage(Request $request, ImageStore $images)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:avatar,cover'],
            'image' => ['required', 'file', 'mimes:'.implode(',', config('cashflow.images.mimes')), 'max:'.config('cashflow.limits.photo_upload_max_kb')],
        ]);

        $user = $request->user();
        $column = $data['kind'].'_path';
        $old = $user->{$column};
        $user->update([$column => $images->storeProfileImage($user, $request->file('image'), $data['kind'])]);
        $images->delete([$old]);

        return new MeResource($user);
    }

    public function deleteImage(Request $request, string $kind, ImageStore $images)
    {
        $user = $request->user();
        $column = $kind.'_path';
        $images->delete([$user->{$column}]);
        $user->update([$column => null]);

        return new MeResource($user);
    }
}
