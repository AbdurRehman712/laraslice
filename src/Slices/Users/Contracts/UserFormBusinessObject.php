<?php

namespace LaraSlice\Slices\Users\Contracts;

use LaraSlice\Core\Base\BaseFormBusinessObject;

class UserFormBusinessObject extends BaseFormBusinessObject
{
    public string $name = '';
    public string $email = '';
    public ?string $password = null;
    public string $status = 'active'; // active, suspended, pending
    public ?string $avatarUrl = null;
    public ?string $cnic = null;
    public array $roles = [];
    public array $roleIds = [];
}
