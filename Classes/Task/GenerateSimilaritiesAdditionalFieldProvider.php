<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Task;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

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
            'label' => 'Start Page ID (Point de départ de l\'analyse)',
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
            'label' => 'Pages à exclure (séparées par des virgules)',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        
        // NOUVEAU : Champ pour recursiveExclusion
        if (!isset($taskInfo['recursiveExclusion'])) {
            if ($task instanceof GenerateSimilaritiesTask) {
                $taskInfo['recursiveExclusion'] = $task->recursiveExclusion;
            } else {
                $taskInfo['recursiveExclusion'] = true; // Par défaut : récursif
            }
        }
        
        $fieldId = 'task_recursiveExclusion';
        $checked = $taskInfo['recursiveExclusion'] ? 'checked="checked"' : '';
        $fieldCode = '<div class="form-check">
            <input type="checkbox" class="form-check-input" name="tx_scheduler[recursiveExclusion]" id="' . $fieldId . '" value="1" ' . $checked . ' />
            <label class="form-check-label" for="' . $fieldId . '">
                Exclure récursivement les sous-pages
            </label>
        </div>
        <small class="form-text text-muted">
            Si coché : exclure la page ET toutes ses sous-pages<br>
            Si décoché : exclure seulement la page, mais analyser ses sous-pages
        </small>';
        
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => 'Exclusion récursive',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        
        // Champ pour minimumSimilarity
        if (!isset($taskInfo['minimumSimilarity'])) {
            if ($task instanceof GenerateSimilaritiesTask) {
                $taskInfo['minimumSimilarity'] = $task->minimumSimilarity;
            } else {
                $taskInfo['minimumSimilarity'] = 0.5; // Valeur par défaut
            }
        }
        
        $fieldId = 'task_minimumSimilarity';
        $fieldCode = '<input type="number" class="form-control" name="tx_scheduler[minimumSimilarity]" id="' . $fieldId . '" value="' . number_format((float)$taskInfo['minimumSimilarity'], 2) . '" step="0.01" min="0" max="1" />';
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => 'Seuil minimum de similarité (0-1)',
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
                'L\'ID de la page de départ doit être un nombre entier positif.',
                FlashMessage::ERROR
            );
            $result = false;
        }
        
        // Validation de minimumSimilarity
        $minimumSimilarity = (float)$submittedData['minimumSimilarity'];
        if ($minimumSimilarity < 0 || $minimumSimilarity > 1) {
            $schedulerModule->addMessage(
                'Le seuil de similarité doit être une valeur entre 0 et 1.',
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
                        'La liste des pages à exclure doit contenir uniquement des ID de pages valides (nombres entiers positifs).',
                        FlashMessage::ERROR
                    );
                    $result = false;
                    break;
                }
            }
        }
        
        // Validation de recursiveExclusion : pas de validation spécifique nécessaire
        // car c'est un booléen géré par la checkbox
        
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
            // Gestion de la checkbox : si pas présente dans $_POST, elle est décochée
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