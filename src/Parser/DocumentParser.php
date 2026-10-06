<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

use Clarilens\Parser\Config\ParserConfig;
use Clarilens\Parser\Exception\SourceDriftException;
use Clarilens\Parser\Extraction\DomContext;
use Clarilens\Parser\Extraction\PdfContext;
use Clarilens\Parser\Extraction\RuleContext;
use Clarilens\Parser\Extraction\RuleRunner;
use Clarilens\Parser\Result\DocumentOrigin;
use Clarilens\Parser\Result\ParseOutcome;
use Clarilens\Parser\Source\SourceLoader;
use DOMDocument;
use InvalidArgumentException;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser as PdfTextExtractor;

/** @implements DocumentParserInterface<ParseRequest, ParseOutcome> */
final readonly class DocumentParser implements DocumentParserInterface
{
    public function __construct(private ?SourceLoader $sourceLoader = null)
    {
    }

    #[\Override]
    public function parse(object $request): ParseOutcome
    {
        if (!$request instanceof ParseRequest) {
            throw new InvalidArgumentException('Invalid parse request.');
        }
        $config = $request->config;
        if ($request->pdfPages !== null) {
            if ($config->parserType !== ParserType::PDF) {
                throw new InvalidArgumentException('PDF pages require PDF parser.');
            }
            return $this->run(new PdfContext($this->validatePdfPages($request->pdfPages)), $config, $request->origin);
        }
        $content = $request->content;
        if ($content === null) {
            if ($config->source === null || $this->sourceLoader === null) {
                throw new InvalidArgumentException('Source and loader are required when content is absent.');
            }
            $content = $this->sourceLoader->load($config->source, $config->parserType);
        }
        if ($config->parserType === ParserType::PDF) {
            return $this->run($this->pdf($content), $config, $request->origin);
        }
        return $this->run($this->dom($content, $config->parserType, $config->namespaces), $config, $request->origin);
    }

    private function run(RuleContext $context, ParserConfig $config, ?DocumentOrigin $origin): ParseOutcome
    {
        $outcome = RuleRunner::run($context, $config->rules, $config->checks, $config->tables);
        if ($config->strict && count($outcome->failures) > 0) {
            throw new SourceDriftException($outcome->failures->items);
        }
        return new ParseOutcome($outcome->values, $outcome->failures, $origin, $outcome->tables);
    }

    /** @param array<string, string> $namespaces */
    private function dom(string $content, ParserType $type, array $namespaces): DomContext
    {
        if (strlen($content) > 5_000_000) {
            throw new InvalidArgumentException('Document is too large.');
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if ($type === ParserType::XML) {
                if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $content)) {
                    throw new InvalidArgumentException('XML DTD is not allowed.');
                }
                $loaded = $document->loadXML($content, LIBXML_NONET);
            } else {
                $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_NONET);
            }
            if (!$loaded) {
                throw new InvalidArgumentException('Invalid document.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new DomContext($type, $document, $namespaces);
    }

    private function pdf(string $content): PdfContext
    {
        if (!str_starts_with($content, '%PDF-') || strlen($content) > 10_000_000) {
            throw new InvalidArgumentException('Invalid PDF document.');
        }
        $settings = new PdfConfig();
        $settings->setDecodeMemoryLimit(20_000_000);
        $settings->setRetainImageContent(false);
        $pages = (new PdfTextExtractor([], $settings))->parseContent($content)->getPages();
        if (count($pages) > 50) {
            throw new InvalidArgumentException('PDF has too many pages.');
        }
        $texts = [];
        foreach ($pages as $page) {
            $text = $page->getText();
            if (strlen($text) > 1_000_000) {
                throw new InvalidArgumentException('PDF page text is too large.');
            }
            $texts[] = $text;
        }
        return new PdfContext($texts);
    }

    /**
     * @param list<string> $pages
     * @return list<string>
     **/
    private function validatePdfPages(array $pages): array
    {
        if ($pages === [] || !array_is_list($pages) || count($pages) > 50) {
            throw new InvalidArgumentException('Invalid PDF pages.');
        }
        foreach ($pages as $page) {
            if (!is_string($page) || strlen($page) > 1_000_000) {
                throw new InvalidArgumentException('Invalid PDF page text.');
            }
        }
        return $pages;
    }
}
