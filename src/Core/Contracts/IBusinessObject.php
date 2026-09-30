<?php

namespace LaraSlice\Core\Contracts;

interface IBusinessObject
{
    /**
     * Convert the business object to an array for JSON/API responses.
     */
    public function toArray(): array;

    /**
     * Create an instance from array data.
     */
    public static function fromArray(array $data): static;
}
