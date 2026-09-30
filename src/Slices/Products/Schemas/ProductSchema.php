<?php

namespace LaraSlice\Slices\Products\Schemas;

use LaraSlice\Schema\SliceSchema;
use LaraSlice\Schema\Field;
use LaraSlice\Schema\Column;

class ProductSchema extends SliceSchema
{
    public static function fields(): array
    {
        return [
            Field::text('title')->label('Product Title')->required()->placeholder('e.g. MacBook Pro M4'),
            Field::decimal('price')->label('Price (USD)')->prefix('$')->placeholder('0.00'),
            Field::text('sku')->label('SKU / Barcode')->placeholder('e.g. MBP-M4-001'),
            Field::number('stock')->label('Inventory Stock')->default(0),
            Field::textarea('description')->label('Product Description')->placeholder('Detailed specifications...'),
            Field::select('status', [
                'draft'    => 'Draft',
                'active'   => 'Active',
                'archived' => 'Archived',
            ])->label('Publication Status')->default('draft'),
        ];
    }

    public static function columns(): array
    {
        return [
            Column::text('id')->label('ID'),
            Column::text('title')->label('Title')->searchable()->sortable(),
            Column::badge('status')->label('Status'),
            Column::money('price', '$')->label('Price'),
            Column::text('sku')->label('SKU'),
            Column::text('stock')->label('Stock'),
        ];
    }
}
