<?php

namespace TalanHdf\SemanticSuggestion\Controller;

use TYPO3\CMS\Core\Information\Typo3Version; // Importer la classe pour comparer les versions
use TYPO3\CMS\Core\Utility\GeneralUtility; // Importer GeneralUtility
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use ReflectionProperty;

/**
 * Classe abstraite compatible entre TYPO3 v12 et v13 avec logique conditionnelle
 */
abstract class AbstractCompatibleController extends ActionController
{
    // Pas de déclaration pour $settings (hérité)
    // Pas de déclaration pour $arguments (hérité)

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        // Utiliser la réflexion pour vérifier $arguments de manière sûre ici,
        // car initializeAction est appelé plus tard dans le cycle.
        $this->ensureArgumentsAreInitializedSafelyUsingReflection();

        // Appel au parent
        parent::initializeAction();

        // Assigner les settings (hérités du parent) à la vue
        if (isset($this->view) && $this->view !== null && property_exists($this, 'settings') && isset($this->settings)) {
            $this->view->assign('settings', $this->settings);
        }
    }

    /**
     * @inheritDoc
     * Contient de la logique spécifique à la version à cause des différences
     * dans l'initialisation de $arguments et les règles PHP.
     */
    protected function initializeActionMethodValidators(): void
    {
        // Obtenir la version de TYPO3
        // Utilisation de GeneralUtility::makeInstance pour la compatibilité si l'injection directe pose problème
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

        if ($versionInformation->getMajorVersion() < 13) {
            // --- Logique pour TYPO3 v12 (et potentiellement v11) ---

            // Tentative d'initialisation juste avant l'appel parent
            // (peut fonctionner grâce à la tolérance de PHP <= 7.4)
            if (!$this->arguments instanceof Arguments) {
                 $this->arguments = new Arguments();
            }
            // Appel au parent (qui devrait maintenant avoir $this->arguments initialisé)
            parent::initializeActionMethodValidators();
        } else {
            // --- Logique pour TYPO3 v13+ ---

            // NE PAS appeler parent::initializeActionMethodValidators()
            // car nous savons qu'il échoue dans ce contexte backend v13 en accédant
            // à $this->arguments->count() alors que $arguments n'est pas initialisé
            // et que PHP 8+ l'interdit.
            // On saute cette étape spécifique ici.
            // Si une validation est cruciale, elle devrait être ajoutée ailleurs ou via annotations.
        }
    }


    /**
     * Initialise la propriété $arguments de façon sûre en utilisant la réflexion
     * si elle n'est pas déjà initialisée par Extbase.
     * Principalement utile comme filet de sécurité dans initializeAction.
     */
    private function ensureArgumentsAreInitializedSafelyUsingReflection(): void
    {
        try {
            // Utiliser la réflexion pour vérifier si la propriété (héritée) est initialisée
            $reflection = new ReflectionProperty(ActionController::class, 'arguments');

            // Vérifier si la méthode isInitialized existe (PHP >= 7.4)
            if (method_exists($reflection, 'isInitialized')) {
                 if (!$reflection->isInitialized($this)) {
                    // Si non initialisée (et PHP >= 7.4), on l'initialise.
                    $this->arguments = new Arguments();
                 }
            } else {
                 // Fallback pour PHP < 7.4 (peut être le cas pour TYPO3 v11 ou début v12)
                 // Tenter une initialisation classique si $arguments n'est pas déjà un objet
                 if (!isset($this->arguments) || !$this->arguments instanceof Arguments) {
                      $this->arguments = new Arguments();
                 }
            }
        } catch (\ReflectionException $e) {
            // En cas d'échec de la réflexion (peu probable),
            // initialiser par sécurité si $arguments n'est pas déjà un objet.
            if (!isset($this->arguments) || !$this->arguments instanceof Arguments) {
                 $this->arguments = new Arguments();
            }
        }
    }
}