<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Source\Url;
use Clarilens\Parser\Validation\Checks;
use InvalidArgumentException;

final readonly class SourceConfig
{
    /** @param list<BrowserAction> $actions */
    public function __construct(
        public SourceType $type,
        public ?string $path = null,
        public ?string $url = null,
        public ?RenderType $render = null,
        public array $actions = [],
        public ?string $readySelector = null,
        public int $timeoutMs = 30000,
    ) {
        if ($type === SourceType::File) {
            if ($path === null || $path === '' || $url !== null || $render !== null || $actions !== [] || $readySelector !== null) {
                throw new InvalidArgumentException('Invalid file source.');
            }
            return;
        }
        $parts = Url::parts($url);
        if ($path !== null || $parts === null || !in_array($parts['scheme'], ['http', 'https'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || $render === null || !array_is_list($actions) || count($actions) > 5) {
            throw new InvalidArgumentException('Invalid web source.');
        }
        if ($render === RenderType::Http && ($actions !== [] || $readySelector !== null)) {
            throw new InvalidArgumentException('HTTP source cannot use browser options.');
        }
        Checks::timeout($timeoutMs);
        if ($readySelector !== null) {
            Checks::selector($readySelector);
        }
        foreach ($actions as $action) {
            if (!$action instanceof BrowserAction) {
                throw new InvalidArgumentException('Invalid browser action.');
            }
        }
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_string($data['type'] ?? null)) {
            throw new InvalidArgumentException('Invalid source config.');
        }

        $type = SourceType::tryFrom($data['type']);
        if ($type === SourceType::File) {
            if (!is_string($data['path'] ?? null) || $data['path'] === '') {
                throw new InvalidArgumentException('Invalid file source.');
            }
            return new self($type, path: $data['path']);
        }

        $render = is_string($data['render'] ?? null) ? RenderType::tryFrom($data['render']) : null;
        $ready = $data['ready'] ?? null;
        $actions = $data['actions'] ?? [];
        $timeout = $data['timeoutMs'] ?? 30000;
        if ($type !== SourceType::Web || Url::parts($data['url'] ?? null) === null
            || $render === null || !is_int($timeout)
            || !is_array($actions) || !array_is_list($actions) || count($actions) > 5
            || ($ready !== null && (!is_array($ready) || !is_string($ready['selector'] ?? null)))
            || ($ready !== null && (strlen($ready['selector']) < 1 || strlen($ready['selector']) > 1000))) {
            throw new InvalidArgumentException('Invalid web source.');
        }
        $actions = array_map(BrowserAction::fromArray(...), $actions);

        return new self(
            $type,
            url: $data['url'],
            render: $render,
            actions: $actions,
            readySelector: $ready['selector'] ?? null,
            timeoutMs: $timeout,
        );
    }

    /** @return array<string, mixed> */
    public function browserOptions(): array
    {
        $options = ['timeoutMs' => $this->timeoutMs];
        if ($this->actions !== []) {
            $options['actions'] = array_map(static fn (BrowserAction $action): array => $action->toArray(), $this->actions);
        }
        if ($this->readySelector !== null) {
            $options['ready'] = ['selector' => $this->readySelector];
        }
        return $options;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->type === SourceType::File) {
            return ['type' => $this->type->value, 'path' => $this->path];
        }
        return [
            'type' => $this->type->value,
            'url' => $this->url,
            'render' => $this->render?->value,
            ...$this->browserOptions(),
        ];
    }
}
