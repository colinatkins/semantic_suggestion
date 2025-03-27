<?php
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TalanHdf\SemanticSuggestion\Controller\SemanticBackendController;

$routes = [];
$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

if ($versionInformation->getMajorVersion() >= 13) { // Condition V13+
    $routes['semantic_suggestion_proximity'] = [
        'path' => '/semantic-suggestion/proximity',
        'target' => SemanticBackendController::class . '::indexAction' // Contrôleur V13
    ];
}
return $routes; // Retourne vide pour V12