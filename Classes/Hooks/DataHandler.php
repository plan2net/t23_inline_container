<?php

namespace Team23\T23InlineContainer\Hooks;

/*
 * This file is part of TYPO3 CMS-based extension "t23_inline_container" by TEAM23.

 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use B13\Container\Hooks\Datahandler\Database as DatahandlerDatabase;
use Team23\T23InlineContainer\Integrity\Sorting;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\MathUtility;

class DataHandler implements SingletonInterface
{
    /**
     * @var array<,int>
     */
    private $postProcessContainerUidList = [];

    public function __construct(
        protected readonly Sorting $sorting,
        protected readonly DatahandlerDatabase $dataHandlerDatabase
    ) {}

    /**
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     */
    public function processDatamap_beforeStart(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler): void
    {
        if (is_array($dataHandler->datamap['tt_content'] ?? null)) {
            foreach ($dataHandler->datamap['tt_content'] as $id => $values) {
                if (!empty($values['tx_t23inlinecontainer_elements']) && MathUtility::canBeInterpretedAsInteger($id)) {
                    $containerUid = (int) $id;
                    $this->postProcessContainerUidList[$containerUid] = $containerUid;
                }
            }
        }
    }

    /**
     * @param array $incomingFieldArray
     * @param string $table
     * @param int|string $id
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @return void
     */
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        $id,
        \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
    ): void {
        // Only handle content elements
        if ($table !== 'tt_content') {
            return;
        }

        // Only relevant if this is a translated record
        $languageUid = (int)($incomingFieldArray['sys_language_uid'] ?? 0);
        if ($languageUid <= 0) {
            return;
        }

        // If this record has a container parent, check if it's a localized one
        $txContainerParent = (int)($incomingFieldArray['tx_container_parent'] ?? 0);
        if ($txContainerParent > 0) {
            // Fetch the parent record
            $parentRecord = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord('tt_content', $txContainerParent);

            // If parent exists and is a localized record, resolve its default language UID
            if (!empty($parentRecord['l18n_parent'])) {
                $defaultParentUid = (int)$parentRecord['l18n_parent'];

                // Rewrite to the default-language container
                $incomingFieldArray['tx_container_parent'] = $defaultParentUid;
            }
        }
    }

    public function processCmdmap_preProcess($command, $table, $id, $value, $pObj, $pasteUpdate): void
    {
        if (in_array($command, ['copy', 'localize']) && $table === 'tt_content') {
            $GLOBALS['TCA']['tt_content']['columns']['tx_t23inlinecontainer_elements']['config']['type'] = 'none';
        }
    }

    public function processCmdmap_postProcess($command, $table, $id, $value, $pObj, $pasteUpdate, $pasteDatamap): void
    {
        if (in_array($command, ['copy', 'localize']) && $table === 'tt_content') {
            $GLOBALS['TCA']['tt_content']['columns']['tx_t23inlinecontainer_elements']['config']['type'] = 'tx_t23inlinecontainer_elements';
        }
    }

    /**
     * Fix container inline elements sorting after everything else has been processes
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @return void
     */
    public function processDatamap_afterAllOperations(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler): void
    {
        // Make sure that container sorting is only update once per container element
        // => Only run sorting update after all operations have been finished
        if (!empty($this->postProcessContainerUidList) && $dataHandler->isOuterMostInstance()) {
            foreach ($this->postProcessContainerUidList as $containerRecordUid) {
                $containerRecord = $this->dataHandlerDatabase->fetchOneRecord($containerRecordUid);
                if (!empty($containerRecord)) {
                    $this->sorting->runForSingleContainer($containerRecord, $containerRecord['CType']);
                }
            }
        }
    }
}
