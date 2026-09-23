<?php

declare(strict_types=1);

namespace Team23\T23InlineContainer\Hooks\Datahandler;

/*
 * This file is part of TYPO3 CMS-based extension "t23_inline_container" by TEAM23.

 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;

#[Autoconfigure(public: true)]
class CommandMapPostProcessingHook extends \B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook
{
    #[\Override]
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
        $pasteUpdate,
        $pasteDatamap
    ): void {
        if ($table === 'tt_content' && $command === 'copy') {
            return;
        }

        parent::processCmdmap_postProcess($command, $table, $id, $value, $dataHandler, $pasteUpdate, $pasteDatamap);
    }
}
