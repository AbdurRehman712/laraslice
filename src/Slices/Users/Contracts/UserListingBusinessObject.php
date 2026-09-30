<?php

namespace LaraSlice\Slices\Users\Contracts;

use LaraSlice\Core\Base\BaseListingBusinessObject;

class UserListingBusinessObject extends BaseListingBusinessObject
{
    public string $name = '';
    public string $email = '';
    public string $status = 'active';
    public ?string $avatarUrl = null;
    public ?string $cnic = null;
    public array $roles = [];
}
