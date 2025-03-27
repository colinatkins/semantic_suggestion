<?php

namespace TalanHdf\SemanticSuggestion\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;

/**
 * Classe abstraite compatible entre TYPO3 v12 et v13
 * Résout les problèmes d'accès aux propriétés typées avant initialisation
 */
abstract class AbstractCompatibleController extends ActionController
{
    /**
     * Override de la propriété settings pour éviter l'erreur d'accès avant initialisation
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        // Vérification sécurisée de la propriété arguments
        $this->initializeArgumentsProperty();
        
        // Appel au parent une seule fois
        parent::initializeAction();
        
        // S'assurer que settings est passé à la vue si elle existe
        // et configurer les chemins de templates si nécessaire
        if (isset($this->view) && $this->view !== null) {
            // Ajouter explicitement les chemins de templates
            if (method_exists($this->view, 'setTemplateRootPaths')) {
                $this->view->setTemplateRootPaths([
                    0 => 'EXT:semantic_suggestion/Resources/Private/Templates/'
                ]);
            }
            
            // Assigner les settings à la vue
            $this->view->assign('settings', $this->settings);
        }
    }

    /**
     * @inheritDoc
     */
    protected function initializeActionMethodValidators(): void
    {
        // Vérification sécurisée de la propriété arguments
        $this->initializeArgumentsProperty();
        
        // Appel au parent
        parent::initializeActionMethodValidators();
    }
    
    /**
     * Initialise la propriété $arguments de façon sûre
     */
    private function initializeArgumentsProperty(): void
    {
        if (property_exists($this, 'arguments') && !isset($this->arguments)) {
            try {
                $reflection = new \ReflectionProperty(ActionController::class, 'arguments');
                if (!$reflection->isInitialized($this)) {
                    $this->arguments = new Arguments();
                }
            } catch (\ReflectionException $e) {
                $this->arguments = new Arguments();
            }
        }
    }
}