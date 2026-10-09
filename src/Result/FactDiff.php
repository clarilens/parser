<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final readonly class FactDiff
{
    /** @param list<string> $added
     * @param list<string> $removed
     * @param list<string> $changed
     */
    public function __construct(public array $added, public array $removed, public array $changed)
    {
    }

    public static function between(ObservedFactBatch $before, ObservedFactBatch $after): self
    {
        $left = self::index($before);
        $right = self::index($after);
        $added = $removed = $changed = [];
        foreach ($left as $key => $value) {
            if (!isset($right[$key])) {
                $removed[] = $key;
            } elseif ($right[$key] !== $value) {
                $changed[] = $key;
            }
        }
        foreach ($right as $key => $_) {
            if (!isset($left[$key])) {
                $added[] = $key;
            }
        }
        return new self($added, $removed, $changed);
    }

    /** @return array<string, string> */
    private static function index(ObservedFactBatch $batch): array
    {
        $entries = [];
        foreach ($batch->facts as $fact) {
            $key = ($fact->groupKey ?? '') . ':' . $fact->field;
            $entries[$key][] = ['present', $fact->value, array_map(static fn (FactEvidence $evidence): array => $evidence->toArray(), $fact->allEvidence())];
        }
        foreach ($batch->resolutions as $resolution) {
            $key = ($resolution->groupKey ?? '') . ':' . $resolution->field;
            $entries[$key][] = [$resolution->state->value, $resolution->reason, array_map(static fn (FactEvidence $evidence): array => $evidence->toArray(), $resolution->evidences)];
        }
        $indexed = [];
        foreach ($entries as $key => $values) {
            $json = array_map(static fn (array $value): string => json_encode($value, JSON_THROW_ON_ERROR), $values);
            sort($json, SORT_STRING);
            $indexed[$key] = hash('sha256', implode("\0", $json));
        }
        ksort($indexed);
        return $indexed;
    }
}
