<?php

declare(strict_types=1);

use B13\Container\Tca\ContainerConfiguration;
use B13\Container\Tca\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

GeneralUtility::makeInstance(Registry::class)->configureContainer(
    new ContainerConfiguration(
        'test_container',
        'Test container',
        'One column, accepts any content element including another container',
        [
            [
                [
                    'name' => 'Content',
                    'colPos' => 200,
                ],
            ],
        ]
    )
);
