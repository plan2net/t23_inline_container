<?php

defined('TYPO3') || die();

/*
 * This file is part of TYPO3 CMS-based extension "t23_inline_container" by TEAM23.

 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['tx-t23inlinecontainer'] = 'Team23\T23InlineContainer\Hooks\DataHandler';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['tx-t23inlinecontainer'] = 'Team23\T23InlineContainer\Hooks\DataHandler';

// The tx_t23inlinecontainer_elements field turns container children into a native inline relation,
// so TYPO3 core copies/moves/localizes them via tx_container_parent by itself. b13/container's own
// copy hook would then process the same children a second time (duplicated, and multiplied for nested
// containers). On TYPO3 v13+ the field type can no longer be toggled off at runtime (DataHandler reads
// the cached TcaSchema), so we disable b13's redundant copy path and let core be the single copier.
unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['tx_container-post-process']);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][\Team23\T23InlineContainer\Backend\FormDataProvider\ContainerChildrenFormDataProvider::class] = [
    'depends' => [
        \TYPO3\CMS\Backend\Form\FormDataProvider\PageTsConfig::class,
    ],
    'before' => [
        \TYPO3\CMS\Backend\Form\FormDataProvider\InlineOverrideChildTca::class,
    ]
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][\Team23\T23InlineContainer\Backend\FormDataProvider\ContainerChildrenLanguageCorrectionFormDataProvider::class] = [
    'depends' => [
        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineData::class,
    ],
];
