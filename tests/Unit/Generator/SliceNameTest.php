<?php

namespace LaraSlice\Tests\Unit\Generator;

use InvalidArgumentException;
use LaraSlice\Generator\SliceName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SliceNameTest extends TestCase
{
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'path traversal' => ['../Admin'],
            'namespace injection' => ['App\\Slices\\Admin'],
            'leading punctuation' => ['-Product'],
            'php punctuation' => ['Product;phpinfo()'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_names_that_cannot_be_safe_slice_identifiers(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        SliceName::canonical($name);
    }

    public function test_it_normalizes_human_readable_names_to_a_php_class_name(): void
    {
        $this->assertSame('PurchaseOrder', SliceName::canonical('Purchase Order'));
        $this->assertSame('DemoInventory', SliceName::canonical('DemoInventories'));
    }
}
