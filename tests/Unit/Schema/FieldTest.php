<?php

namespace LaraSlice\Tests\Unit\Schema;

use BadMethodCallException;
use InvalidArgumentException;
use LaraSlice\Schema\Field;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
    public function test_it_rejects_unsafe_field_names_and_unknown_configuration_methods(): void
    {
        try {
            Field::make('name" autofocus="autofocus');
            $this->fail('Expected unsafe field name to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('lowercase snake_case', $exception->getMessage());
        }

        $this->expectException(BadMethodCallException::class);
        Field::text('name')->requierd();
    }

    public function test_it_escapes_select_labels_and_values_and_renders_configured_rows_and_autofocus(): void
    {
        $field = Field::select('status', [
            "draft' onmouseover='alert(1)" => '<img src=x onerror=alert(1)>',
        ])->label('Review status')->required();

        $select = $field->renderBlatUi();
        $this->assertStringNotContainsString('<img', $select);
        $this->assertStringContainsString('&lt;img', $select);
        $this->assertStringContainsString('draft&#039; onmouseover=&#039;alert(1)', $select);
        $this->assertStringContainsString(' required', $select);

        $textarea = Field::textarea('notes')->rows(999)->renderBlatUi();
        $this->assertStringContainsString('rows="20"', $textarea);

        $input = Field::text('title')->autofocus()->renderBlatUi();
        $this->assertStringContainsString(' autofocus', $input);
    }
}
