<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Parser\ParserType;
use InvalidArgumentException;
use RuntimeException;

final readonly class SourceLoader
{
    public function __construct(
        private SourcePolicy $policy,
        private string $baseDir,
        private ?HttpWebClient $httpClient = null,
        private ?WebClient $browserClient = null,
    ) {
    }

    public function load(SourceConfig $source, ParserType $format): string
    {
        if ($source->type === SourceType::File) {
            $root = realpath($this->policy->fileRoot);
            $file = realpath($this->baseDir . '/' . $source->path);
            if ($root === false || $file === false
                || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
                || !is_file($file) || filesize($file) > SourcePolicy::MAX_SOURCE_BYTES) {
                throw new InvalidArgumentException('Source file is outside the allowed root or too large.');
            }
            $content = file_get_contents($file);
            if ($content === false || strlen($content) > SourcePolicy::MAX_SOURCE_BYTES) {
                throw new RuntimeException('Cannot read source or source is too large.');
            }
            return $content;
        }

        if ($source->render === RenderType::Browser) {
            $client = $this->browserClient ?? new BrowserWebClient(dirname(__DIR__, 2) . '/dist/render-page.js');
            return $client->render(new BrowserRequest(
                $source->url,
                $source->browserOptions()['actions'] ?? [],
                $source->readySelector,
                $source->timeoutMs,
                $this->policy->allowedWebHosts,
            ));
        }

        $client = $this->httpClient ?? new HttpWebClient();
        return $client->fetch(new HttpFetchRequest(
            $source->url,
            $this->policy->allowedWebHosts,
            $format === ParserType::PDF ? 10_000_000 : 5_000_000,
            $source->timeoutMs,
        ));
    }
}
