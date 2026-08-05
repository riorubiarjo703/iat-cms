<?php

namespace Tests\Unit;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use PHPUnit\Framework\TestCase;

class SnippetEnumsTest extends TestCase
{
    public function test_type_options_map_values_to_labels(): void
    {
        $this->assertSame([
            'script' => 'Script',
            'style' => 'Style',
            'meta' => 'Meta',
            'html' => 'HTML',
        ], SnippetType::options());
    }

    /**
     * The list page sorts by `cases()` order rather than keeping a separate
     * sort map, so declaration order is load-bearing: it must match the order
     * the positions appear in the rendered document.
     */
    public function test_positions_are_declared_in_document_order(): void
    {
        $this->assertSame(
            ['head', 'body_start', 'body_end'],
            array_column(SnippetPosition::cases(), 'value'),
        );
    }

    public function test_position_options_map_values_to_labels(): void
    {
        $this->assertSame([
            'head' => 'Head',
            'body_start' => 'Body Start',
            'body_end' => 'Body End',
        ], SnippetPosition::options());
    }
}
