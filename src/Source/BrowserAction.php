<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use InvalidArgumentException;

final readonly class BrowserAction
{
    public function __construct(
        public BrowserActionType $type,
        public string $selector,
    ) {
        if ($selector === '' || strlen($selector) > 1000) {
            throw new InvalidArgumentException('Invalid browser action selector.');
        }
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['type'] ?? null)
            || !is_string($data['selector'] ?? null)
            || ($type = BrowserActionType::tryFrom($data['type'])) === null) {
            throw new InvalidArgumentException('Invalid browser action.');
        }

        return new self($type, $data['selector']);
    }

    /** @return array{type: string, selector: string} */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'selector' => $this->selector];
    }
}
