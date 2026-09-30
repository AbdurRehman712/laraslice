<?php

namespace LaraSlice\Generator;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class SliceName
{
    /** Return the canonical class name for a user-supplied slice name. */
    public static function canonical(string $name): string
    {
        $name = trim($name);

        if ($name === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $name)) {
            throw new InvalidArgumentException('Use a slice name that starts with a letter and contains only letters, numbers, spaces, hyphens, or underscores.');
        }

        $canonical = Str::studly(Str::singular($name));

        if ($canonical === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $canonical)) {
            throw new InvalidArgumentException('The supplied slice name does not produce a valid PHP class name.');
        }

        return $canonical;
    }
}
