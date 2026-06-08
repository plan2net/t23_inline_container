<?php

namespace Team23\T23InlineContainer\Backend\FormDataProvider;

use B13\Container\Tca\Registry;
use Team23\T23InlineContainer\Helper\ColPosHelper;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Class ContainerChildrenFormDataProvider: customize container inline children
 *
 * @package Team23\T23InlineContainer\Backend\FormDataProvider
 */
class ContainerChildrenFormDataProvider implements FormDataProviderInterface
{

    public function __construct(private readonly ColPosHelper $colPosHelper) {}

    /**
     * @param array $result
     * @return array
     */
    public function addData(array $result): array
    {
        $childProcessed = false;
        if (!empty($result['inlineParentUid']) && !empty($result['inlineParentFieldName'])
            && $result['inlineParentFieldName'] === 'tx_t23inlinecontainer_elements') {
            $result = $this->processContainerChildTca($result);
            $childProcessed = true;
        }

        // Inject this record's CType into its inline field config so child compilations
        // can read it from inlineParentConfig after TYPO3 passes it through the signed AJAX context.
        if (isset($result['processedTca']['columns']['tx_t23inlinecontainer_elements'])) {
            $cType = null;
            // For new records processed as inline children, TCAdefaults.CType is the
            // forced type (set by processContainerChildTca after DatabaseRowInitializeNew ran).
            if ($childProcessed && $result['command'] === 'new') {
                $cType = $result['pageTsConfig']['TCAdefaults.']['tt_content.']['CType'] ?? null;
            }
            if (!$cType) {
                $raw = $result['databaseRow']['CType'] ?? null;
                $cType = is_array($raw) ? ($raw[0] ?? null) : $raw;
            }
            if ($cType) {
                $result['processedTca']['columns']['tx_t23inlinecontainer_elements']['config']['t3ContainerCType'] = $cType;
            }
        }

        return $result;
    }

    /**
     * Process the TCA for container children: only show available colPos choices and allowed CType choices
     *
     * @param array $result
     * @return array
     */
    protected function processContainerChildTca(array $result): array
    {
        $containerId = (int)$result['inlineParentUid'];
        $parentRecord = BackendUtility::getRecord('tt_content', $containerId);

        if (empty($parentRecord) && !MathUtility::canBeInterpretedAsInteger($result['inlineParentUid'])) {
            // Primary: CType injected by the parent's own compilation, arrives via signed AJAX context
            $cType = $result['inlineParentConfig']['t3ContainerCType'] ?? null;
            if ($cType) {
                $parentRecord = ['CType' => $cType];
            } else {
                // Secondary: infer from inline structure (works when nearest saved ancestor is unambiguous)
                $inferredCType = $this->inferParentCTypeFromStructure($result);
                if ($inferredCType !== null) {
                    $parentRecord = ['CType' => $inferredCType];
                }
            }
        }

        if (!empty($parentRecord)) {
            $containerRegistry = GeneralUtility::makeInstance(Registry::class);
            if ($containerId > 0) {
                $availableColumns = $this->colPosHelper->getAvailableColPos($containerId, (int)$result['vanillaUid']);
            } else {
                $availableColumns = array_values($containerRegistry->getAvailableColumns($parentRecord['CType']));
            }
            if (!empty($availableColumns)) {
                // Determine allowed colPos values and column config for selected column (if not empty)
                $allowedColPosList = [];
                $selectedColPos = null;
                $columnConfig = $availableColumns[0];
                if ($result['command'] === 'new') {
                    $selectedColPos = (int) $columnConfig['colPos'];
                    // Set the default colPos value to the first allowed colPos choice
                    $result['pageTsConfig']['TCAdefaults.']['tt_content.']['colPos'] = $selectedColPos;
                } elseif ($result['command'] === 'edit' && !empty($result['databaseRow']['colPos'])) {
                    $selectedColPos = (int) $result['databaseRow']['colPos'];
                    foreach ($availableColumns as $tmpConfig) {
                        $allowedColPosList[] = (int)$tmpConfig['colPos'];
                    }
                    if (!in_array($selectedColPos, $allowedColPosList, true)) {
                        $selectedColPos = (int) current($allowedColPosList);
                        $result['databaseRow']['colPos'] = $selectedColPos;
                    }
                }

                // Restrict colPos dropdown via TSconfig keepItems — TcaSelectItems applies this
                // after itemsProcFunc and addItems, so it acts as an authoritative final filter.
                $result['pageTsConfig']['TCEFORM.']['tt_content.']['colPos.']['keepItems'] = implode(
                    ',',
                    array_column($availableColumns, 'colPos')
                );

                if ($selectedColPos) {
                    $contentDefenderConfiguration = $containerRegistry->getContentDefenderConfiguration(
                        $parentRecord['CType'],
                        $selectedColPos
                    );
                }
                if (!empty($contentDefenderConfiguration)) {
                    $result = $this->processContentDefenderConfiguration($contentDefenderConfiguration, $result);
                }
            }
        }
        return $result;
    }

    /**
     * Process the TCA for container children: only show allowed CType/list_type items
     *
     * @param array $contentDefenderConfiguration
     * @param array $result
     * @return array
     */
    protected function processContentDefenderConfiguration(array $contentDefenderConfiguration, array $result): array
    {
        $allowedConfiguration = array_intersect_key($contentDefenderConfiguration['allowed.'] ?? [], $result['processedTca']['columns']);
        $disallowedConfiguration = array_intersect_key($contentDefenderConfiguration['disallowed.'] ?? [], $result['processedTca']['columns']);

        if (!empty($allowedConfiguration) || !empty($disallowedConfiguration)) {
            $typo3version = GeneralUtility::makeInstance(Typo3Version::class);
            $ctypeValueKey = ($typo3version->getBranch() >= 12) ? 'value' : 1;

            foreach ($allowedConfiguration as $field => $value) {
                $allowedValues = GeneralUtility::trimExplode(',', $value);
                $result['processedTca']['columns'][$field]['config']['items'] = array_filter(
                    $result['processedTca']['columns'][$field]['config']['items'],
                    static function ($item) use ($allowedValues, $ctypeValueKey) {
                        return in_array($item[$ctypeValueKey], $allowedValues, true);
                    }
                );
            }

            foreach ($disallowedConfiguration as $field => $value) {
                $disallowedValues = GeneralUtility::trimExplode(',', $value);
                $result['processedTca']['columns'][$field]['config']['items'] = array_filter(
                    $result['processedTca']['columns'][$field]['config']['items'],
                    static function ($item) use ($disallowedValues, $ctypeValueKey) {
                        return !in_array($item[$ctypeValueKey], $disallowedValues, true);
                    }
                );
            }
            $cTypeItemList = $result['processedTca']['columns']['CType']['config']['items'];
            // Remove the itemsProcFunc (set by EXT:t23_inline_container), because we have a fixed list of allowed CType items
            if (!empty($result['inlineParentConfig']['overrideChildTca']['columns']['CType']['config']['itemsProcFunc'])) {
                unset($result['inlineParentConfig']['overrideChildTca']['columns']['CType']['config']['itemsProcFunc']);
            }
            $availableCTypes = array_column($cTypeItemList, $ctypeValueKey);

            // Set the default CType value to the first allowed CType choice
            $result['pageTsConfig']['TCAdefaults.']['tt_content.']['CType'] = current($availableCTypes);
        }
        return $result;
    }

    private function inferParentCTypeFromStructure(array $result): ?string
    {
        $containerRegistry = GeneralUtility::makeInstance(Registry::class);
        $structure = $result['inlineStructure']['stable'] ?? [];

        foreach (array_reverse($structure) as $level) {
            if (($level['table'] ?? '') !== 'tt_content') {
                continue;
            }
            if (!MathUtility::canBeInterpretedAsInteger($level['uid']) || (int)$level['uid'] <= 0) {
                continue;
            }
            $ancestorRecord = BackendUtility::getRecord('tt_content', (int)$level['uid']);
            if (empty($ancestorRecord)) {
                continue;
            }
            $allowedContainerCTypes = [];
            foreach ($containerRegistry->getAvailableColumns($ancestorRecord['CType']) as $column) {
                $defConfig = $containerRegistry->getContentDefenderConfiguration($ancestorRecord['CType'], $column['colPos']);
                foreach (GeneralUtility::trimExplode(',', $defConfig['allowed.']['CType'] ?? '', true) as $cType) {
                    if ($containerRegistry->getAvailableColumns($cType) !== []) {
                        $allowedContainerCTypes[$cType] = $cType;
                    }
                }
            }
            return count($allowedContainerCTypes) === 1 ? array_key_first($allowedContainerCTypes) : null;
        }
        return null;
    }
}
