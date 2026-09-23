<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Container Test',
    'description' => 'Registers a container used by the functional tests',
    'category' => 'example',
    'version' => '13.0.1',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'container' => '',
        ],
    ],
];
