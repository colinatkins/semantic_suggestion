<?php

namespace TalanHdf\SemanticSuggestion\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use ReflectionProperty; // Assurez-vous que ce 'use' est présent

/**
 * Classe abstraite compatible entre TYPO3 v12 et v13
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

    // !! SUPPRIMEZ LA MÉTHODE initializeActionMethodValidators CI-DESSOUS !!
    /*
     * @inheritDoc
     */
    /*
    protected function initializeActionMethodValidators(): void
    {
        // Appel direct au parent.
        parent::initializeActionMethodValidators();
    }
    */


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
            // Note: Sur certaines versions de PHP/TYPO3, isInitialized peut ne pas exister
            // ou nécessiter PHP >= 7.4. Ajout d'une condition pour vérifier son existence.
            if (method_exists($reflection, 'isInitialized') && !$reflection->isInitialized($this)) {
                // Si non initialisée, on l'initialise nous-mêmes.
                $this->arguments = new Arguments();
            } elseif (!method_exists($reflection, 'isInitialized')) {
                 // Fallback pour versions PHP < 7.4 ou si isInitialized n'existe pas
                 // Tenter une initialisation classique si $arguments n'est pas déjà un objet
                 if (!isset($this->arguments) || !$this->arguments instanceof Arguments) {
                      $this->arguments = new Arguments();
                 }
            }
        } catch (\ReflectionException $e) {
            // En cas d'échec de la réflexion, initialiser par sécurité
            if (!isset($this->arguments) || !$this->arguments instanceof Arguments) {
                 $this->arguments = new Arguments();
            }
        }
    }
}