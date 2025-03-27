<?php
defined('TYPO3') or die();

// Module registration for TYPO3 13 has moved to Configuration/Backend/Modules.php
// This file is intentionally left mostly empty to maintain compatibility with both TYPO3 v12 and v13

// For TYPO3 v12 compatibility, we'll check if the old registration method exists
if (method_exists(\TYPO3\CMS\Extbase\Utility\ExtensionUtility::class, 'registerModule')) {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'SemanticSuggestion',
        'web',
        'semantic_suggestion',
        '',
        [
            \TalanHdf\SemanticSuggestion\Controller\SemanticBackendController::class => 'index',
        ],
        [
            'access' => 'user,group',
            'icon'   => 'EXT:semantic_suggestion/Resources/Public/Icons/module-semantic-suggestion.svg',
            'labels' => 'LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_mod.xlf',
        ]
    );
}