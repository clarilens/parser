<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

use Clarilens\Parser\Validation\Checks;

final readonly class ExtractedValue
{
    public function __construct(
        public string $field,
        public string $value,
        public NodeLocation|PageLocation|JsonLocation|null $location = null,
        public ?string $raw = null,
    ) {
        Checks::field($field);
    }
}
