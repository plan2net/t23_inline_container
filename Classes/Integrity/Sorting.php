<?php

declare(strict_types=1);

namespace Team23\T23InlineContainer\Integrity;

use B13\Container\Domain\Model\Container;
use TYPO3\CMS\Core\Database\Connection;

class Sorting extends \B13\Container\Integrity\Sorting
{
    protected function fixChildrenSortingUpdateRequired(Container $container, array $colPosByCType): bool
    {
        if (parent::fixChildrenSortingUpdateRequired($container, $colPosByCType)) {
            return true;
        }

        if (!$this->hasInterleavedRecords($container)) {
            return false;
        }

        $containerRecord = $container->getContainerRecord();
        $this->errors[] = '- pid ' . $containerRecord['pid']
            . ', container uid ' . $containerRecord['uid'] . ' must be fixed';
        return true;
    }

    private function hasInterleavedRecords(Container $container): bool
    {
        $containerRecord = $container->getContainerRecord();
        $lastRecord = $this->containerService->getAfterContainerRecord($container);
        if ((int)$lastRecord['uid'] === (int)$containerRecord['uid']) {
            return false;
        }

        $descendantUids = [(int)$containerRecord['uid'] => true];
        $records = $this->getRecordsInSortingRange($containerRecord, $container->getLanguage(), $lastRecord);
        foreach ($records as $record) {
            if (!isset($descendantUids[(int)$record['tx_container_parent']])) {
                return true;
            }
            $descendantUids[(int)$record['uid']] = true;
        }

        return false;
    }

    private function getRecordsInSortingRange(array $containerRecord, int $language, array $lastRecord): array
    {
        $queryBuilder = $this->database->getQueryBuilder();
        $pid = $queryBuilder->createNamedParameter((int)$containerRecord['pid'], Connection::PARAM_INT);
        $language = $queryBuilder->createNamedParameter($language, Connection::PARAM_INT);
        $firstSorting = $queryBuilder->createNamedParameter((int)$containerRecord['sorting'], Connection::PARAM_INT);
        $lastSorting = $queryBuilder->createNamedParameter((int)$lastRecord['sorting'], Connection::PARAM_INT);
        return $queryBuilder
            ->select('uid', 'tx_container_parent')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $pid),
                $queryBuilder->expr()->eq('sys_language_uid', $language),
                $queryBuilder->expr()->gt('sorting', $firstSorting),
                $queryBuilder->expr()->lte('sorting', $lastSorting)
            )
            ->orderBy('sorting')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

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
}
