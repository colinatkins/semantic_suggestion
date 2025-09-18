<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Task;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class GenerateSimilaritiesAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * Gets additional fields to render in the scheduler backend module
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];
        
        // Champ pour startPageId
        if (empty($taskInfo['startPageId'])) {
            if ($task instanceof GenerateSimilaritiesTask) {
                $taskInfo['startPageId'] = $task->startPageId;
            } else {
                $taskInfo['startPageId'] = 1; // Valeur par défaut
            }
        }
        
        $fieldId = 'task_startPageId';
        $fieldCode = '<input type="number" class="form-control" name="tx_scheduler[startPageId]" id="' . $fieldId . '" value="' . (int)$taskInfo['startPageId'] . '" />';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.task.start_page_id', 'semantic_suggestion') ?? 'Start Page ID (Analysis starting point)',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        
        // Champ pour excludePages
        if (empty($taskInfo['excludePages'])) {
            if ($task instanceof GenerateSimilaritiesTask) {
                $taskInfo['excludePages'] = $task->excludePages;
            } else {
                $taskInfo['excludePages'] = ''; // Valeur par défaut
            }
        }
        
        $fieldId = 'task_excludePages';
        $fieldCode = '<input type="text" class="form-control" name="tx_scheduler[excludePages]" id="' . $fieldId . '" value="' . htmlspecialchars($taskInfo['excludePages']) . '" placeholder="ex: 42,56,78" />';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.task.exclude_pages', 'semantic_suggestion') ?? 'Pages to exclude (comma separated)',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        
        // NEW: Field for recursiveExclusion
        if (!isset($taskInfo['recursiveExclusion'])) {
            if ($task instanceof GenerateSimilaritiesTask) {
                $taskInfo['recursiveExclusion'] = $task->recursiveExclusion;
            } else {
                $taskInfo['recursiveExclusion'] = true; // Default: recursive
            }
        }
        
        $fieldId = 'task_recursiveExclusion';
        $checked = $taskInfo['recursiveExclusion'] ? 'checked="checked"' : '';
        $fieldCode = '<div class="form-check">
            <input type="checkbox" class="form-check-input" name="tx_scheduler[recursiveExclusion]" id="' . $fieldId . '" value="1" ' . $checked . ' />
            <label class="form-check-label" for="' . $fieldId . '">
                ' . (LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.task.recursive_exclusion.help', 'semantic_suggestion') ?? 'Exclude recursively sub-pages') . '
            </label>
        </div>
        <small class="form-text text-muted">
            ' . (LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.task.recursive_exclusion.description', 'semantic_suggestion') ?? 'If checked: exclude the page AND all its sub-pages<br>If unchecked: exclude only the page, but analyze its sub-pages') . '
        </small>';
        
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.task.recursive_exclusion', 'semantic_suggestion') ?? 'Recursive exclusion',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        
        // Champ pour minimumSimilarity
        if (!isset($taskInfo['minimumSimilarity'])) {
            if ($task instanceof GenerateSimilaritiesTask) {
                $taskInfo['minimumSimilarity'] = $task->minimumSimilarity;
            } else {
                $taskInfo['minimumSimilarity'] = 0.1; // Valeur par défaut ajustée pour TF-IDF
            }
        }
        
        $fieldId = 'task_minimumSimilarity';
        $fieldCode = '<input type="number" class="form-control" name="tx_scheduler[minimumSimilarity]" id="' . $fieldId . '" value="' . number_format((float)$taskInfo['minimumSimilarity'], 2) . '" step="0.01" min="0" max="1" />';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.task.minimum_similarity', 'semantic_suggestion') ?? 'Minimum similarity threshold (0-1)',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        
        return $additionalFields;
    }
    
    /**
     * Validates the additional fields' values
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        $result = true;
        
        // Validation de startPageId
        if ((int)$submittedData['startPageId'] <= 0) {
            $schedulerModule->addMessage(
                LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.validation.invalid_start_page', 'semantic_suggestion') ?? 'The start page ID must be a positive integer.',
                FlashMessage::ERROR
            );
            $result = false;
        }
        
        // Validation de minimumSimilarity
        $minimumSimilarity = (float)$submittedData['minimumSimilarity'];
        if ($minimumSimilarity < 0 || $minimumSimilarity > 1) {
            $schedulerModule->addMessage(
                LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.validation.invalid_similarity', 'semantic_suggestion') ?? 'The similarity threshold must be a value between 0 and 1.',
                FlashMessage::ERROR
            );
            $result = false;
        }
        
        // Validation de excludePages
        if (!empty($submittedData['excludePages'])) {
            $excludePages = GeneralUtility::trimExplode(',', $submittedData['excludePages'], true);
            foreach ($excludePages as $pageId) {
                if (!is_numeric($pageId) || (int)$pageId <= 0) {
                    $schedulerModule->addMessage(
                        LocalizationUtility::translate('LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_be.xlf:scheduler.validation.invalid_exclude_pages', 'semantic_suggestion') ?? 'The exclude pages list must contain only valid page IDs (positive integers).',
                        FlashMessage::ERROR
                    );
                    $result = false;
                    break;
                }
            }
        }
        
        // Validation of recursiveExclusion: no specific validation needed
        // as it's a boolean handled by checkbox
        
        return $result;
    }
    
    /**
     * Saves additional field values in task object
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof GenerateSimilaritiesTask) {
            $task->startPageId = (int)$submittedData['startPageId'];
            $task->excludePages = $submittedData['excludePages'];
            $task->minimumSimilarity = (float)$submittedData['minimumSimilarity'];
            // Handle checkbox: if not present in $_POST, it's unchecked
            $task->recursiveExclusion = isset($submittedData['recursiveExclusion']) && $submittedData['recursiveExclusion'] === '1';
        }
    }
    
    /**
     * Gets the language service
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}