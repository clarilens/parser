<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserIntegrationTest extends TestCase
{
    /** @return iterable<string, array{callable(): void}> */
    public static function scenarios(): iterable
    {
        foreach (require __DIR__ . '/cases.php' as $name => $scenario) {
            yield $name => [$scenario];
        }
    }

    #[DataProvider('scenarios')]
    public function testScenario(callable $scenario): void
    {
        $scenario();
    }
}
