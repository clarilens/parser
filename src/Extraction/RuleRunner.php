<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Config\FieldCheck;
use Clarilens\Parser\Extraction\ExtractionRule;
use Clarilens\Parser\Extraction\RuleContext;
use Clarilens\Parser\Result\Collection;
use Clarilens\Parser\Result\ParseOutcome;
use Clarilens\Parser\Result\RuleFailure;
use InvalidArgumentException;

final class RuleRunner
{
    /**
     * @param list<ExtractionRule> $rules
     * @param list<FieldCheck> $checks
     * @param list<TableGridRule> $tables
     **/
    public static function run(RuleContext $context, array $rules, array $checks = [], array $tables = []): ParseOutcome
    {
        $values = [];
        $failures = [];
        foreach ($rules as $rule) {
            if (!$rule->supports($context->type())) {
                throw new InvalidArgumentException('Rule does not support parser type.');
            }
            $found = $rule->extract($context);
            array_push($values, ...$found);
            if ($found === [] && $rule->required()) {
                $failures[] = new RuleFailure($rule->field(), 'No values found.');
            }
        }
        foreach ($checks as $check) {
            array_push($failures, ...$check->verify($values));
        }
        $grids = [];
        foreach ($tables as $table) {
            if (!$context instanceof DomContext) {
                throw new InvalidArgumentException('Table grids require HTML.');
            }
            $found = $table->extract($context);
            array_push($grids, ...$found);
            if ($found === [] && $table->required) {
                $failures[] = new RuleFailure($table->name, 'No tables found.');
            }
        }
        return new ParseOutcome(new Collection($values), new Collection($failures), tables: new Collection($grids));
    }
}
