<?php
defined('TYPO3') or die();

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;

(static function() {

    $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
    $isTypo3Version12OrLower = $versionInformation->getMajorVersion() < 13;

    // Plugin Frontend 'Suggestions' (Inconditionnel)
    ExtensionUtility::configurePlugin(
        'SemanticSuggestion', 'Suggestions',
        [\TalanHdf\SemanticSuggestion\Controller\SuggestionsController::class => 'list'],
        []  
    );

    // Plugin 'SemanticBackend' (Conditionnel)
    if ($isTypo3Version12OrLower) {
        ExtensionUtility::configurePlugin(
            'SemanticSuggestion', 'SemanticBackend',
            [\TalanHdf\SemanticSuggestion\Controller\LegacySemanticBackendController::class => 'index'], // V12
            [\TalanHdf\SemanticSuggestion\Controller\LegacySemanticBackendController::class => 'index']  // V12 Non-cacheable
        );
    } else {
        ExtensionUtility::configurePlugin(
            'SemanticSuggestion', 'SemanticBackend',
            [\TalanHdf\SemanticSuggestion\Controller\SemanticBackendController::class => 'index'], // V13
            [\TalanHdf\SemanticSuggestion\Controller\SemanticBackendController::class => 'index']  // V13 Non-cacheable
        );
    }

    // Hooks TCA (Inconditionnel)
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \TalanHdf\SemanticSuggestion\Hooks\DataHandlerHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \TalanHdf\SemanticSuggestion\Hooks\DataHandlerHook::class;

    // TypoScript (Inconditionnel)
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup('@import "EXT:semantic_suggestion/Configuration/TypoScript/setup.typoscript"');
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptConstants('@import "EXT:semantic_suggestion/Configuration/TypoScript/constants.typoscript"');

    // Cache (Inconditionnel)
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['semantic_suggestion'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['semantic_suggestion'] = [
            'frontend' => VariableFrontend::class, 'backend' => FileBackend::class,
            'options' => ['defaultLifetime' => 86400], 'groups' => ['pages']
        ];
    }

    // Scheduler (Inconditionnel)
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TalanHdf\SemanticSuggestion\Task\GenerateSimilaritiesTask::class] = [
        'extension' => 'semantic_suggestion', /* ... title, description ... */
    ];

    // Logger (Logique de chemin conditionnelle ou simplifiée)
    $logFilePath = 'typo3temp/logs/semantic_suggestion.log'; // Chemin simple et fiable
    // Vous pouvez essayer de rendre le chemin absolu :
    // $logFilePath = GeneralUtility::getFileAbsFileName($logFilePath);
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['TalanHdf']['SemanticSuggestion']['writerConfiguration'] = [
        LogLevel::DEBUG => [ FileWriter::class => [ 'logFile' => $logFilePath ] ],
    ];

})();