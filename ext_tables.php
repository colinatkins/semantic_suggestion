<?php
defined('TYPO3') or die();

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
$isV12 = $versionInformation->getMajorVersion() < 13;
$methodExists = method_exists(\TYPO3\CMS\Extbase\Utility\ExtensionUtility::class, 'registerModule');

// Commentez ou supprimez tout le bloc IF ou juste l'appel registerModule
/*
if ($isV12 && $methodExists) {
    try {
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'semantic_suggestion', // Clé Extension
            'web',
            'semantic_suggestion',
            '',
            [ \TalanHdf\SemanticSuggestion\Controller\LegacySemanticBackendController::class => 'index'],
            [
                'access' => 'user,group',
                'labels' => 'LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_mod.xlf',
            ]
        );
    } catch (\Exception $e) {
         error_log('ERROR[semantic_suggestion]: Exception during module registration in ext_tables.php: ' . $e->getMessage());
    }
}
*/
?>