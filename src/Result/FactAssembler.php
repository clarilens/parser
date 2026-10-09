<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

final class FactAssembler
{
    /** @param list<ObservedFact> $observations
     * @param list<FactResolution> $resolutions
     * @param list<string> $requiredFields
     * @return array{list<ObservedFact>, list<FactResolution>, array<string, bool>}
     */
    public static function assemble(array $observations, array $resolutions, array $requiredFields): array
    {
        $groups = [];
        foreach ($observations as $observation) {
            $key = ($observation->groupKey ?? '') . "\0" . $observation->field;
            $groups[$key][] = $observation;
        }
        $facts = [];
        foreach ($groups as $items) {
            $values = array_unique(array_map(static fn (ObservedFact $fact): string => $fact->value, $items));
            if (count($values) !== 1) {
                $first = $items[0];
                $evidence = [];
                foreach ($items as $item) {
                    array_push($evidence, ...$item->allEvidence());
                }
                $resolutions[] = new FactResolution($first->field, FactState::Conflict, $first->field, $first->groupKey, 'conflicting observations', $evidence);
                continue;
            }
            $first = $items[0];
            $evidence = [];
            foreach ($items as $item) {
                array_push($evidence, ...$item->allEvidence());
            }
            $facts[] = new ObservedFact($first->field, $first->value, $first->evidence, $first->groupKey, $evidence, $first->rawInput, $first->operations);
        }
        $complete = [];
        foreach ($facts as $fact) {
            $complete[$fact->groupKey ?? ''] ??= true;
        }
        foreach ($resolutions as $resolution) {
            $complete[$resolution->groupKey ?? ''] ??= true;
            if ($resolution->state !== FactState::NotApplicable) {
                $complete[$resolution->groupKey ?? ''] = false;
            }
        }
        $complete[''] ??= true;
        foreach (array_keys($complete) as $group) {
            if ($group !== '') {
                continue;
            }
            foreach ($requiredFields as $field) {
                $present = false;
                foreach ($facts as $fact) {
                    if (($fact->groupKey ?? '') === $group && $fact->field === $field) {
                        $present = true;
                        break;
                    }
                }
                if (!$present) {
                    $complete[$group] = false;
                    $resolutions[] = new FactResolution($field, FactState::Absent, $field, $group === '' ? null : $group, 'required field missing');
                }
            }
        }
        return [$facts, $resolutions, $complete];
    }
}
