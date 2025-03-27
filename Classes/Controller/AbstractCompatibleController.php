<?php

namespace TalanHdf\SemanticSuggestion\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments; // Assurez-vous que cette ligne 'use' est présente

/**
 * Classe abstraite compatible entre TYPO3 v12 et v13
 * Résout les problèmes d'accès aux propriétés typées avant initialisation
 */
abstract class AbstractCompatibleController extends ActionController
{
    // Compat V12 V13, suppprimer TOUTE LA DECLARATION DE $settings CI-DESSOUS :
    /*
     * Settings
     *
     * @var array // Annotation phpDoc pour la clarté
     */
    // protected $settings = []; // Pas de type hint natif pour compatibilité v12


    /**
     * Arguments de l'action.
     * Déclarée ici pour s'assurer qu'elle existe, sans type hint natif.
     * L'initialisation est gérée dans les méthodes initialize*.
     * @var Arguments|null
     */
    protected $arguments;

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        // S'assurer que $arguments est initialisé avant l'appel parent si nécessaire
        $this->ensureArgumentsAreInitialized();

        // Appel au parent
        parent::initializeAction();

        // Assigner les settings (hérités du parent) à la vue
        if (isset($this->view) && $this->view !== null) {
            $this->view->assign('settings', $this->settings);
        }
    }

    /**
     * @inheritDoc
     */
    protected function initializeActionMethodValidators(): void
    {
        // Point crucial : Assurer l'initialisation AVANT l'appel parent
        $this->ensureArgumentsAreInitialized();

        // Appel au parent qui contient la ligne $this->arguments->count()
        parent::initializeActionMethodValidators();
    }

    /**
     * Initialise la propriété $arguments si elle n'est pas déjà un objet Arguments.
     */
    private function ensureArgumentsAreInitialized(): void
    {
        if (!$this->arguments instanceof Arguments) {
            $this->arguments = new Arguments();
        }
    }
}