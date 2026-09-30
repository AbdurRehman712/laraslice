<?php

namespace LaraSlice\Slices\Users\Controllers;

use LaraSlice\Core\Base\BaseSliceWebController;
use LaraSlice\Core\Contracts\IFormDataService;
use LaraSlice\Core\Contracts\IListingDataService;
use LaraSlice\Slices\Users\Services\UserSliceService;
use LaraSlice\Slices\Users\Contracts\UserFormBusinessObject;
use LaraSlice\Slices\Users\Contracts\UserFilterBusinessObject;
use LaraSlice\Slices\Roles\Models\Role;

class UserWebController extends BaseSliceWebController
{
    protected UserSliceService $service;

    public function __construct(UserSliceService $service)
    {
        $this->service = $service;
    }

    protected function getService(): IFormDataService&IListingDataService
    {
        return $this->service;
    }

    protected function getFormClass(): string
    {
        return UserFormBusinessObject::class;
    }

    protected function getFilterClass(): string
    {
        return UserFilterBusinessObject::class;
    }

    protected function getViewPrefix(): string
    {
        return 'users::';
    }

    protected function getRoutePrefix(): string
    {
        return 'users.';
    }

    public function create()
    {
        $formClass = $this->getFormClass();
        $form = new $formClass();
        $availableRoles = class_exists(Role::class) ? Role::all() : [];

        return view($this->getViewPrefix() . 'form', [
            'form'           => $form,
            'isNew'          => true,
            'routePrefix'    => $this->getRoutePrefix(),
            'availableRoles' => $availableRoles,
        ]);
    }

    public function edit(string|int $id)
    {
        $form = $this->getService()->getItemById($id);

        if (!$form) {
            return redirect()->route($this->getRoutePrefix() . 'index')->with('error', 'User not found');
        }

        $availableRoles = class_exists(Role::class) ? Role::all() : [];

        return view($this->getViewPrefix() . 'form', [
            'form'           => $form,
            'isNew'          => false,
            'routePrefix'    => $this->getRoutePrefix(),
            'availableRoles' => $availableRoles,
        ]);
    }
}
