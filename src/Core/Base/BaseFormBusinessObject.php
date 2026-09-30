<?php

namespace LaraSlice\Core\Base;

use LaraSlice\Core\Contracts\IBusinessObject;

abstract class BaseFormBusinessObject implements IBusinessObject
{
    public string|int|null $id = null;

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): static
    {
        $instance = new static();
        foreach ($data as $key => $value) {
            // Support camelCase and snake_case
            $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            } elseif (property_exists($instance, $camel)) {
                $instance->{$camel} = $value;
            }
        }

        // Seamless bridge between name and title for quick-add drawers & API contracts
        if (property_exists($instance, 'title') && (empty($instance->title) || !isset($data['title']))) {
            $candidate = $data['name'] ?? $data['label'] ?? null;
            if (!empty($candidate)) {
                $instance->title = (string) $candidate;
            }
        }
        if (property_exists($instance, 'name') && (empty($instance->name) || !isset($data['name']))) {
            $candidate = $data['title'] ?? $data['label'] ?? null;
            if (!empty($candidate)) {
                $instance->name = (string) $candidate;
            }
        }

        return $instance;
    }
}
