<?php

// Utiliser la classe Typo3Version pour la comparaison
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Définition du module backend pour TYPO3 v13+ uniquement
$modules = [];
$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

if ($versionInformation->getMajorVersion() >= 13) {
    $modules['web_SemanticSuggestion'] = [ // Même clé que dans ext_tables.php
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user,group',
        'workspaces' => 'live',
        'path' => '/module/web/SemanticSuggestion', // Chemin du module
        'labels' => 'LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_mod.xlf',
        'icon' => 'EXT:semantic_suggestion/Resources/Public/Icons/module-semantic-suggestion.svg',
        'iconIdentifier' => 'module-semantic-suggestion',
        'extensionName' => 'SemanticSuggestion', // Nom de l'extension
        'controllerActions' => [
            // Utilise le contrôleur V13
            \TalanHdf\SemanticSuggestion\Controller\SemanticBackendController::class => [
                'index', 'list' // Adaptez les actions si nécessaire
            ],
        ],
        'view' => [ // Configuration de la vue (peut être partagée mais définie ici pour V13+)
            'templateRootPaths' => [
                100 => 'EXT:semantic_suggestion/Resources/Private/Templates/',
            ],
            'partialRootPaths' => [
                100 => 'EXT:semantic_suggestion/Resources/Private/Partials/',
            ],
            'layoutRootPaths' => [
                100 => 'EXT:semantic_suggestion/Resources/Private/Layouts/',
            ],
        ],
    ];
}

// Retourne le tableau (vide pour V12, rempli pour V13+)
return $modules;