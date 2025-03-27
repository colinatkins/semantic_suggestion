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
    /**
     * Settings
     *
     * @var array // Annotation phpDoc pour la clarté
     */
    protected $settings = []; // Pas de type hint natif pour compatibilité v12

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

        // Assigner les settings à la vue pour le frontend (principalement)
        // et potentiellement d'autres initialisations si nécessaire ici.
        if (isset($this->view) && $this->view !== null) {
             // Le code pour setTemplateRootPaths est commenté car normalement géré
             // par Configuration/Backend/Modules.php pour le backend
             /*
            if (method_exists($this->view, 'setTemplateRootPaths')) {
                $this->view->setTemplateRootPaths([
                    0 => 'EXT:semantic_suggestion/Resources/Private/Templates/'
                ]);
            }
            */

            // Assigner les settings à la vue
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
     * Cette méthode est plus simple et plus directe que la précédente avec réflexion.
     */
    private function ensureArgumentsAreInitialized(): void
    {
        if (!$this->arguments instanceof Arguments) {
             // Si $arguments n'est pas déjà un objet Arguments (inclut le cas où il est null),
             // on l'initialise.
            $this->arguments = new Arguments();
        }
    }
}