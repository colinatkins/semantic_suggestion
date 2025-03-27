<?php

namespace TalanHdf\SemanticSuggestion\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use ReflectionProperty; // Ajouter la classe ReflectionProperty

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
        // Ajouter une vérification de sécurité pour $this->settings
        if (isset($this->view) && $this->view !== null && property_exists($this, 'settings') && isset($this->settings)) {
            $this->view->assign('settings', $this->settings);
        }
    }

    /**
     * @inheritDoc
     */
    protected function initializeActionMethodValidators(): void
    {
        // IMPORTANT : Ne PAS appeler ensureArgumentsAreInitialized() ici.
        // On laisse le processus standard d'Extbase initialiser $arguments.
        // L'erreur précédente dans count() ne devrait plus se produire si notre
        // code n'interfère pas ici.

        // Appel direct au parent.
        parent::initializeActionMethodValidators();
    }

    /**
     * Initialise la propriété $arguments de façon sûre en utilisant la réflexion
     * si elle n'est pas déjà initialisée par Extbase.
     * Principalement utile comme filet de sécurité dans initializeAction.
     */
    private function ensureArgumentsAreInitializedSafelyUsingReflection(): void
    {
        try {
            // Vérifier via Réflexion si la propriété $arguments (héritée) est initialisée
            $reflection = new ReflectionProperty(ActionController::class, 'arguments');
            if (!$reflection->isInitialized($this)) {
                // Si elle n'est PAS initialisée à ce stade (ce qui serait peut-être
                // inattendu mais possible), on l'initialise nous-mêmes.
                $this->arguments = new Arguments();
            }
        } catch (\ReflectionException $e) {
            // En cas d'échec de la réflexion (très peu probable),
            // on tente une initialisation classique par sécurité si $arguments n'est pas déjà un objet.
            if (!isset($this->arguments) || !$this->arguments instanceof Arguments) {
                 $this->arguments = new Arguments();
            }
        }
    }
}