<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

use Clarilens\Parser\Config\DocumentFactConfig;
use Clarilens\Parser\Result\FactDiff;
use Clarilens\Parser\Result\ObservedFactBatch;
use DateTimeImmutable;

final readonly class SavedDocumentValidator
{
    public function __construct(private DocumentFactParser $parser = new DocumentFactParser())
    {
    }

    public function validate(DocumentFactConfig $config, string $savedHtml, string $sourceId, string $url, DateTimeImmutable $observedAt): ObservedFactBatch
    {
        return $this->parser->parse($config, $savedHtml, $sourceId, $url, $observedAt);
    }

    public function compare(DocumentFactConfig $before, DocumentFactConfig $after, string $savedHtml, string $sourceId, string $url, DateTimeImmutable $observedAt): FactDiff
    {
        return FactDiff::between(
            $this->validate($before, $savedHtml, $sourceId, $url, $observedAt),
            $this->validate($after, $savedHtml, $sourceId, $url, $observedAt),
        );
    }
}
