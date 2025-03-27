<?php
defined('TYPO3') or die();

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// 1. Est-ce que ce fichier est bien exécuté ?
// error_log('DEBUG[semantic_suggestion]: ext_tables.php START'); // Décommentez pour tester

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
$isV12 = $versionInformation->getMajorVersion() < 13;
$methodExists = method_exists(\TYPO3\CMS\Extbase\Utility\ExtensionUtility::class, 'registerModule');

// 2. La condition est-elle correcte ?
// error_log('DEBUG[semantic_suggestion]: ext_tables.php - isV12: ' . ($isV12 ? 'Yes' : 'No') . ', methodExists: ' . ($methodExists ? 'Yes' : 'No')); // Décommentez pour tester

if ($isV12 && $methodExists) {
    // 3. Est-ce qu'on entre dans le IF ?
    // error_log('DEBUG[semantic_suggestion]: ext_tables.php INSIDE IF - Registering module...'); // Décommentez pour tester

    try {
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            // --- POINT POTENTIEL ---
            // 'SemanticSuggestion', // Nom Extension (UpperCamelCase)
            'semantic_suggestion', // <-- ESSAYEZ AVEC LA CLÉ (lowercase_underscore)

            'web',
            'semantic_suggestion', // Module Key
            '', // Position
            [ \TalanHdf\SemanticSuggestion\Controller\LegacySemanticBackendController::class => 'index'], // Controller
            [ // Config
                'access' => 'user,group',
                // --- POINTS POTENTIELS ---
                'icon'   => 'EXT:semantic_suggestion/Resources/Public/Icons/module-semantic-suggestion.svg', // Le fichier existe ? Chemin correct ?
                'labels' => 'LLL:EXT:semantic_suggestion/Resources/Private/Language/locallang_mod.xlf', // Le fichier existe ? Chemin correct ? Valide XML/XLF ?
            ]
        );
        // 4. L'enregistrement s'est-il terminé sans exception ?
        // error_log('DEBUG[semantic_suggestion]: ext_tables.php Module registration call finished.'); // Décommentez pour tester
    } catch (\Exception $e) {
        // 5. Y a-t-il eu une exception ?
         error_log('ERROR[semantic_suggestion]: Exception during module registration in ext_tables.php: ' . $e->getMessage()); // Important si ça échoue
         // Vous pourriez vouloir logger plus de détails comme $e->getTraceAsString()
    }
} else {
     // 6. Pourquoi n'entre-t-on pas dans le IF ?
     // error_log('DEBUG[semantic_suggestion]: ext_tables.php Condition for v12 module registration NOT met.'); // Décommentez pour tester
}
?>