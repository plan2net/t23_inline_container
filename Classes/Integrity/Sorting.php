<?php

declare(strict_types=1);

namespace Team23\T23InlineContainer\Integrity;

use B13\Container\Domain\Model\Container;

class Sorting extends \B13\Container\Integrity\Sorting
{
    public function runForSingleContainer($containerRecord, $cType): void
    {
        $columns = $this->tcaRegistry->getAvailableColumns($cType);
        $colPosByCType[$cType] = [];
        foreach ($columns as $column) {
            $colPosByCType[$cType][] = $column['colPos'];
        }
        $this->unsetContentDefenderConfiguration($cType);
        $this->fixChildrenSorting([$containerRecord], $colPosByCType, false, false);
    }

    protected function fixChildrenSortingUpdateRequired(Container $container, array $colPosByCType): bool
    {
        // A moved container can still precede its children while unrelated records sit between them.
        return true;
    }
}
