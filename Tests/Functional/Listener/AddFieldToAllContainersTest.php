<?php

declare(strict_types=1);

namespace Team23\T23InlineContainer\Tests\Functional\Listener;

use PHPUnit\Framework\Attributes\Test;
use Team23\T23InlineContainer\Listener\AddFieldToAllContainers;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AddFieldToAllContainersTest extends FunctionalTestCase
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

    #[Test]
    public function keepsTheChangesOfEarlierListeners(): void
    {
        $tca = $GLOBALS['TCA'];
        $tca['tt_content']['columns']['tx_earlier_listener'] = ['config' => ['type' => 'json']];
        $event = new AfterTcaCompilationEvent($tca);

        (new AddFieldToAllContainers())($event);

        self::assertArrayHasKey('tx_earlier_listener', $event->getTca()['tt_content']['columns']);
    }
}
