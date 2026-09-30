<?php

namespace LaraSlice\Slices\Roles\Controllers;

use LaraSlice\Core\Base\BaseSliceWebController;
use LaraSlice\Core\Contracts\IFormDataService;
use LaraSlice\Core\Contracts\IListingDataService;
use LaraSlice\Slices\Roles\Services\RoleSliceService;
use LaraSlice\Slices\Roles\Contracts\RoleFormBusinessObject;
use LaraSlice\Slices\Roles\Contracts\RoleFilterBusinessObject;
use LaraSlice\Slices\Roles\Models\Permission;

class RoleWebController extends BaseSliceWebController
{
    protected RoleSliceService $service;

    public function __construct(RoleSliceService $service)
    {
        $this->service = $service;
    }

    protected function getService(): IFormDataService&IListingDataService
    {
        return $this->service;
    }

    protected function getFormClass(): string
    {
        return RoleFormBusinessObject::class;
    }

    protected function getFilterClass(): string
    {
        return RoleFilterBusinessObject::class;
    }

    protected function getViewPrefix(): string
    {
        return 'roles::';
    }

    protected function getRoutePrefix(): string
    {
        return 'roles.';
    }

    public function create()
    {
        $formClass = $this->getFormClass();
        $form = new $formClass();
        app(\LaraSlice\Core\Discovery\SliceManager::class)->syncPermissions();
        $permissions = Permission::all()->groupBy('group');

        return view($this->getViewPrefix() . 'form', [
            'form'              => $form,
            'isNew'             => true,
            'routePrefix'       => $this->getRoutePrefix(),
            'groupedPermissions'=> $permissions,
        ]);
    }

    public function edit(string|int $id)
    {
        $form = $this->getService()->getItemById($id);

        if (!$form) {
            return redirect()->route($this->getRoutePrefix() . 'index')->with('error', 'Role not found');
        }

        app(\LaraSlice\Core\Discovery\SliceManager::class)->syncPermissions();
        $permissions = Permission::all()->groupBy('group');

        return view($this->getViewPrefix() . 'form', [
            'form'              => $form,
            'isNew'             => false,
            'routePrefix'       => $this->getRoutePrefix(),
            'groupedPermissions'=> $permissions,
        ]);
    }
}
