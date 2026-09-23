<?php

declare(strict_types=1);

namespace Team23\T23InlineContainer\Tests\Functional\Datahandler;

use B13\Container\Integrity\Sorting as ContainerSorting;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ContainerSortingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'backend',
        'core',
        'frontend',
    ];

    protected array $testExtensionsToLoad = [
        'b13/container',
        'team23/t23-inline-container',
        __DIR__ . '/../Fixtures/Extensions/container_test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUser.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function childCreatedInTheInlineFieldIsSortedInsideItsContainer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithOneChild.csv');

        $newChildUid = $this->createChildInInlineField(2, [3]);

        self::assertSame(
            [
                ['uid' => 1, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
                ['uid' => $newChildUid, 'parent' => 2],
                ['uid' => 4, 'parent' => 0],
            ],
            $this->contentInSortingOrder()
        );
    }

    #[Test]
    public function existingInterleavedChildrenAreSortedInsideTheirContainer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithInterleavedChildren.csv');

        $this->get(ContainerSorting::class)->run(false, false, 1);

        self::assertSame(
            [
                ['uid' => 1, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
                ['uid' => 5, 'parent' => 2],
                ['uid' => 4, 'parent' => 0],
                ['uid' => 6, 'parent' => 0],
            ],
            $this->contentInSortingOrder()
        );
    }

    #[Test]
    public function newElementAfterAContainerIsSortedAfterAChildAddedInTheInlineField(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithOneChild.csv');

        $newChildUid = $this->createChildInInlineField(2, [3]);
        $newUid = $this->createElementAfter(2);

        self::assertSame(
            [
                ['uid' => 1, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
                ['uid' => $newChildUid, 'parent' => 2],
                ['uid' => $newUid, 'parent' => 0],
                ['uid' => 4, 'parent' => 0],
            ],
            $this->contentInSortingOrder()
        );
    }

    #[Test]
    public function movingAContainerTakesItsChildrenAlong(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithOneChild.csv');

        $this->moveAfter(2, 4);

        self::assertSame(
            [
                ['uid' => 1, 'parent' => 0],
                ['uid' => 4, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
            ],
            $this->contentInSortingOrder()
        );
    }

    #[Test]
    public function copyingAContainerCopiesEachChildExactlyOnce(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithOneChild.csv');

        $copiedContainerUid = $this->copyAfter(2, 4);
        $copiedChildUids = $this->childUidsOf($copiedContainerUid);

        self::assertCount(1, $copiedChildUids);
        self::assertSame(
            [
                ['uid' => 1, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
                ['uid' => 4, 'parent' => 0],
                ['uid' => $copiedContainerUid, 'parent' => 0],
                ['uid' => $copiedChildUids[0], 'parent' => $copiedContainerUid],
            ],
            $this->contentInSortingOrder()
        );
    }

    #[Test]
    public function copyingNestedContainersCopiesEachDescendantExactlyOnce(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/NestedContainers.csv');

        $copiedOuterUid = $this->copyAfter(2, 5);
        $copiedInnerUid = $this->childUidsOf($copiedOuterUid)[0] ?? 0;
        $copiedTextUid = $this->childUidsOf($copiedInnerUid)[0] ?? 0;

        self::assertCount(1, $this->childUidsOf($copiedOuterUid));
        self::assertCount(1, $this->childUidsOf($copiedInnerUid));
        self::assertSame(
            [
                ['uid' => 1, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
                ['uid' => 4, 'parent' => 3],
                ['uid' => 5, 'parent' => 0],
                ['uid' => $copiedOuterUid, 'parent' => 0],
                ['uid' => $copiedInnerUid, 'parent' => $copiedOuterUid],
                ['uid' => $copiedTextUid, 'parent' => $copiedInnerUid],
            ],
            $this->contentInSortingOrder()
        );
    }

    #[Test]
    public function translatingAContainerTranslatesEachChildExactlyOnce(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithOneChild.csv');
        $this->writeSiteConfigurationWithSecondLanguage();

        $this->localize(2, 1);

        self::assertCount(1, $this->translationsOf(2));
        self::assertCount(1, $this->translationsOf(3));
    }

    private function writeSiteConfigurationWithSecondLanguage(): void
    {
        $siteDirectory = $this->instancePath . '/typo3conf/sites/test';
        GeneralUtility::mkdir_deep($siteDirectory);
        file_put_contents(
            $siteDirectory . '/config.yaml',
            Yaml::dump([
                'rootPageId' => 1,
                'base' => '/',
                'languages' => [
                    [
                        'title' => 'English',
                        'enabled' => true,
                        'languageId' => 0,
                        'base' => '/',
                        'locale' => 'en_US.UTF-8',
                    ],
                    [
                        'title' => 'German',
                        'enabled' => true,
                        'languageId' => 1,
                        'base' => '/de/',
                        'locale' => 'de_DE.UTF-8',
                        'fallbackType' => 'strict',
                    ],
                ],
            ], 99, 2)
        );
    }

    private function localize(int $uid, int $languageUid): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [],
            ['tt_content' => [$uid => ['localize' => $languageUid]]],
            $GLOBALS['BE_USER']
        );
        $dataHandler->process_cmdmap();
    }

    private function translationsOf(int $uid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('l18n_parent', $uid)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    #[Test]
    public function containerCreatedTogetherWithItsChildKeepsTheChildInside(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ContainerWithOneChild.csv');

        [$newContainerUid, $newChildUid] = $this->createContainerWithChild();

        self::assertSame(
            [
                ['uid' => $newContainerUid, 'parent' => 0],
                ['uid' => $newChildUid, 'parent' => $newContainerUid],
                ['uid' => 1, 'parent' => 0],
                ['uid' => 2, 'parent' => 0],
                ['uid' => 3, 'parent' => 2],
                ['uid' => 4, 'parent' => 0],
            ],
            $this->contentInSortingOrder()
        );
    }

    private function createContainerWithChild(): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tt_content' => [
                    'NEWCONTAINER' => [
                        'pid' => 1,
                        'CType' => 'test_container',
                        'header' => 'Container created with a child',
                        'colPos' => 0,
                        'sys_language_uid' => 0,
                        'tx_t23inlinecontainer_elements' => 'NEWCHILD',
                    ],
                    'NEWCHILD' => [
                        'pid' => 1,
                        'CType' => 'text',
                        'header' => 'Child created with its container',
                        'colPos' => 200,
                        'tx_container_parent' => 'NEWCONTAINER',
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            [],
            $GLOBALS['BE_USER']
        );
        $dataHandler->process_datamap();

        return [
            (int)$dataHandler->substNEWwithIDs['NEWCONTAINER'],
            (int)$dataHandler->substNEWwithIDs['NEWCHILD'],
        ];
    }

    private function createChildInInlineField(int $containerUid, array $existingChildUids): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tt_content' => [
                    $containerUid => [
                        'tx_t23inlinecontainer_elements' => implode(',', [...$existingChildUids, 'NEW1']),
                    ],
                    'NEW1' => [
                        'pid' => 1,
                        'CType' => 'text',
                        'header' => 'Child added in the inline field',
                        'colPos' => 200,
                        'tx_container_parent' => $containerUid,
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            [],
            $GLOBALS['BE_USER']
        );
        $dataHandler->process_datamap();

        return (int)$dataHandler->substNEWwithIDs['NEW1'];
    }

    private function createElementAfter(int $uid): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tt_content' => [
                    'NEW2' => [
                        'pid' => -$uid,
                        'CType' => 'text',
                        'header' => 'Element created after the container',
                        'colPos' => 0,
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            [],
            $GLOBALS['BE_USER']
        );
        $dataHandler->process_datamap();

        return (int)$dataHandler->substNEWwithIDs['NEW2'];
    }

    private function moveAfter(int $uid, int $targetUid): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $this->pasteCommand($uid, 'move', $targetUid), $GLOBALS['BE_USER']);
        $dataHandler->process_cmdmap();
    }

    private function copyAfter(int $uid, int $targetUid): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $this->pasteCommand($uid, 'copy', $targetUid), $GLOBALS['BE_USER']);
        $dataHandler->process_cmdmap();

        return (int)$dataHandler->copyMappingArray['tt_content'][$uid];
    }

    private function pasteCommand(int $uid, string $command, int $targetUid): array
    {
        return [
            'tt_content' => [
                $uid => [
                    $command => [
                        'action' => 'paste',
                        'target' => -$targetUid,
                        'update' => [
                            'colPos' => 0,
                            'sys_language_uid' => 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function childUidsOf(int $containerUid): array
    {
        return array_column(
            array_filter(
                $this->contentInSortingOrder(),
                static fn (array $row): bool => $row['parent'] === $containerUid
            ),
            'uid'
        );
    }

    private function contentInSortingOrder(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'tx_container_parent')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->orderBy('sorting')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'uid' => (int)$row['uid'],
                'parent' => (int)$row['tx_container_parent'],
            ],
            $rows
        );
    }
}
