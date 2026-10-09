<?php

declare(strict_types=1);

namespace Clarilens\Parser\Extraction;

use Clarilens\Parser\Config\OperationRegistry;
use Clarilens\Parser\Result\FactEvidence;
use Clarilens\Parser\Result\FactResolution;
use Clarilens\Parser\Result\FactState;
use Clarilens\Parser\Result\ObservedFact;
use Clarilens\Parser\Result\TableGrid;
use Clarilens\Parser\Result\TableLocation;
use DateTimeImmutable;
use InvalidArgumentException;

final class TableFactProjector
{
    /** @param array<string, mixed> $projection
     * @return array{list<ObservedFact>, list<FactResolution>}
     */
    public static function project(TableGrid $grid, array $projection, string $url, DateTimeImmutable $observedAt, string $sourceId, OperationRegistry $registry = new OperationRegistry()): array
    {
        if (!is_int($projection['headerRow'] ?? null) || !is_int($projection['keyColumn'] ?? null) || !is_string($projection['field'] ?? null)) {
            throw new InvalidArgumentException('Invalid table projection.');
        }
        $facts = $resolutions = [];
        $headers = $grid->rows[$projection['headerRow']] ?? [];
        foreach ($grid->rows as $rowIndex => $row) {
            if ($rowIndex <= $projection['headerRow']) {
                continue;
            }
            $key = $row[$projection['keyColumn']]->text ?? '';
            if ($key === '') {
                $resolutions[] = new FactResolution($projection['field'], FactState::Error, $grid->name, (string) $rowIndex, 'missing row key');
                continue;
            }
            if (isset($projection['skip']) && $key === $projection['skip']) {
                $keyCell = $row[$projection['keyColumn']];
                $resolutions[] = new FactResolution($projection['field'], FactState::NotApplicable, $grid->name, $key, 'evidenced row skip', [new FactEvidence($url, $observedAt, $key, new TableLocation($keyCell->nodePath, $keyCell->row, $keyCell->column, $keyCell->originRow ?? $keyCell->row, $keyCell->originColumn ?? $keyCell->column, $headers[$projection['keyColumn']]->text ?? ''), $sourceId)]);
                continue;
            }
            foreach ($row as $column => $cell) {
                if ($column === $projection['keyColumn']) {
                    continue;
                }
                $header = $headers[$column]->text ?? '';
                if ($header === '' || $cell->text === '') {
                    $resolutions[] = new FactResolution($projection['field'], FactState::Error, $grid->name, $key . ':' . $column, 'missing header or cell');
                    continue;
                }
                $group = $key . ':' . $header;
                $evidence = new FactEvidence($url, $observedAt, $cell->text, new TableLocation($cell->nodePath, $cell->row, $cell->column, $cell->originRow ?? $cell->row, $cell->originColumn ?? $cell->column, $header), $sourceId);
                try {
                    $value = FactTransform::apply($cell->text, $projection['transforms'] ?? [], $registry);
                    $facts[] = new ObservedFact($projection['field'], $value, $evidence, $group, rawInput: $cell->text);
                } catch (\Throwable $error) {
                    $resolutions[] = new FactResolution($projection['field'], FactState::Error, $grid->name, $group, 'row ' . $rowIndex . ': ' . $error->getMessage(), [$evidence]);
                }
            }
        }
        return [$facts, $resolutions];
    }
}
