<?php

declare(strict_types=1);

namespace Clarilens\Parser\Parser;

use Clarilens\Parser\Config\ParserConfig;
use Clarilens\Parser\Config\RuleRegistry;
use Clarilens\Parser\Source\HttpWebClient;
use Clarilens\Parser\Source\SourceLoader;
use Clarilens\Parser\Source\SourcePolicy;
use Clarilens\Parser\Source\SourceType;
use Clarilens\Parser\Source\WebClient;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ParserFactory
{
    /** @throws JsonException */
    public static function fromConfigFile(
        string $configFile,
        ?SourcePolicy $policy = null,
        ?RuleRegistry $registry = null,
        ?HttpWebClient $httpClient = null,
        ?WebClient $browserClient = null,
    ): ConfiguredParser {
        $policy ??= new SourcePolicy(dirname($configFile));
        if (!is_file($configFile) || filesize($configFile) > 100_000) {
            throw new InvalidArgumentException('Config file is missing or too large.');
        }
        $json = file_get_contents($configFile);
        if ($json === false) {
            throw new RuntimeException('Cannot read config.');
        }
        $config = ParserConfig::fromJson($json, $registry);
        if ($config->source === null) {
            throw new InvalidArgumentException('Config file requires a source.');
        }
        $loader = new SourceLoader($policy, dirname($configFile), $httpClient, $browserClient);
        $content = $config->source->type === SourceType::File
            ? $loader->load($config->source, $config->parserType)
            : null;
        return new ConfiguredParser(new DocumentParser($loader), new ParseRequest($config, $content));
    }
}
