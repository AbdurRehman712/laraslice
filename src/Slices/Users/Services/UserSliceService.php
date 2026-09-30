<?php

namespace LaraSlice\Slices\Users\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use LaraSlice\Core\Base\BaseSliceService;
use LaraSlice\Core\Contracts\IBusinessObject;
use LaraSlice\Core\Contracts\IFilterObject;
use LaraSlice\Slices\Users\Models\User;
use LaraSlice\Slices\Users\Contracts\UserFormBusinessObject;
use LaraSlice\Slices\Users\Contracts\UserListingBusinessObject;
use LaraSlice\Slices\Users\Contracts\UserFilterBusinessObject;

class UserSliceService extends BaseSliceService
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function mapToForm(Model $model): IBusinessObject
    {
        /** @var User $model */
        $form = new UserFormBusinessObject();
        $form->id = $model->id;
        $form->name = $model->name;
        $form->email = $model->email;
        $form->status = $model->status;
        $form->avatarUrl = $model->avatar_url;
        $form->cnic = $model->cnic;

        $roleIds = [];
        try {
            if (method_exists($model, 'roles')) {
                $roleIds = $model->roles ? $model->roles->pluck('id')->map(fn($id) => (int) $id)->toArray() : [];
            }
        } catch (\Throwable $e) {}

        if (empty($roleIds) && isset($model->id)) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('role_user')) {
                    $roleIds = \Illuminate\Support\Facades\DB::table('role_user')
                        ->where('user_id', $model->id)
                        ->pluck('role_id')
                        ->map(fn($id) => (int) $id)
                        ->toArray();
                }
            } catch (\Throwable $e) {}
        }

        $form->roles = $roleIds;
        $form->roleIds = $roleIds;
        return $form;
    }

    protected function mapToListing(Model $model): IBusinessObject
    {
        /** @var User $model */
        $listing = new UserListingBusinessObject();
        $listing->id = $model->id;
        $listing->name = $model->name;
        $listing->email = $model->email;
        $listing->status = $model->status;
        $listing->avatarUrl = $model->avatar_url;
        $listing->cnic = $model->cnic;
        $listing->createdAt = $model->created_at ? $model->created_at->toIso8601String() : null;

        $roleNames = [];
        try {
            if (method_exists($model, 'roles')) {
                $roleNames = $model->roles ? $model->roles->pluck('name')->toArray() : [];
            }
        } catch (\Throwable $e) {}

        if (empty($roleNames) && isset($model->id)) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('role_user') && \Illuminate\Support\Facades\Schema::hasTable('roles')) {
                    $roleNames = \Illuminate\Support\Facades\DB::table('role_user')
                        ->join('roles', 'role_user.role_id', '=', 'roles.id')
                        ->where('role_user.user_id', $model->id)
                        ->pluck('roles.name')
                        ->toArray();
                }
            } catch (\Throwable $e) {}
        }

        $listing->roles = $roleNames;
        return $listing;
    }

    protected function beforeSave(IBusinessObject $form, Model $model, bool $isNew): void
    {
        /** @var UserFormBusinessObject $form */
        /** @var User $model */
        if (!empty($form->password)) {
            $model->password = Hash::make($form->password);
        } elseif ($isNew) {
            $model->password = Hash::make('Secret123!');
        }

        $model->avatar_url = $form->avatarUrl;
        $model->cnic = $form->cnic;
    }

    protected function afterSave(IBusinessObject $form, Model $model, bool $isNew): void
    {
        /** @var UserFormBusinessObject $form */
        /** @var User $model */
        $roles = !empty($form->roles) ? $form->roles : ($form->roleIds ?? null);
        if ($roles !== null) {
            $roleIds = array_map('intval', (array) $roles);
            if (method_exists($model, 'roles')) {
                $model->roles()->sync($roleIds);
            } elseif (isset($model->id) && \Illuminate\Support\Facades\Schema::hasTable('role_user')) {
                \Illuminate\Support\Facades\DB::table('role_user')->where('user_id', $model->id)->delete();
                $rows = [];
                foreach ($roleIds as $rId) {
                    if ($rId > 0) {
                        $rows[] = ['role_id' => $rId, 'user_id' => $model->id];
                    }
                }
                if (!empty($rows)) {
                    \Illuminate\Support\Facades\DB::table('role_user')->insert($rows);
                }
            }
        }
    }

    protected function applyFilters(Builder $query, IFilterObject $filter): void
    {
        parent::applyFilters($query, $filter);

        if ($filter instanceof UserFilterBusinessObject) {
            if ($filter->status) {
                $query->where('status', $filter->status);
            }
            if ($filter->role) {
                $query->whereHas('roles', fn($q) => $q->where('slug', $filter->role));
            }
        }
    }

    protected function applySearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }
}
