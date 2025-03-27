<?php

use function Symfony\Component\DependencyInjection\Loader\Configurator\service; // <--- AJOUTER CETTE LIGNE
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TalanHdf\SemanticSuggestion\Controller\LegacySemanticBackendController;
use TalanHdf\SemanticSuggestion\Controller\SemanticBackendController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Core\Environment;
use Monolog\Logger; // Assurez-vous que Monolog est importé si utilisé directement
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public(false); // Mettre public(false) comme défaut

    $services->load('TalanHdf\\SemanticSuggestion\\', '../Classes/*')
        ->exclude('../Classes/Controller/*');

    // --- Enregistrements Inconditionnels ---
    // Rendus publics car souvent nécessaires directement (ex: Controllers, Tasks)
    $services->set(TalanHdf\SemanticSuggestion\Controller\SuggestionsController::class)->public(true);
    $services->set(TalanHdf\SemanticSuggestion\Task\GenerateSimilaritiesTask::class)->public(true); // Assurez-vous que la Task est publique si appelée par le scheduler

    // Services généralement utilisés en interne, peuvent rester privés sauf besoin spécifique
    $services->set(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class)->public(true); // Rendre public si nécessaire ailleurs
    $services->set(TalanHdf\SemanticSuggestion\Service\SuggestionService::class);
    $services->set(TalanHdf\SemanticSuggestion\Service\UtilityService::class);
    $services->set(TalanHdf\SemanticSuggestion\Service\LanguageService::class);
    $services->set(TalanHdf\SemanticSuggestion\Service\StopWordsService::class);
    $services->set(TalanHdf\SemanticSuggestion\Hooks\DataHandlerHook::class);

    // Rendre les services Core nécessaires publics pour l'injection
    $services->set(TYPO3\CMS\Core\Log\LogManager::class)->public(true);
    $services->set(TYPO3\CMS\Core\Messaging\FlashMessageService::class)->public(true);
    $services->set(TYPO3\CMS\Backend\Template\ModuleTemplateFactory::class)->public(true);
    $services->set(TYPO3\CMS\Core\Context\Context::class)->public(true);
    $services->set(TYPO3\CMS\Core\Site\SiteFinder::class)->public(true);
    $services->set(TYPO3\CMS\Core\Resource\FileRepository::class)->public(true);
    $services->set(TYPO3\CMS\Core\Domain\Repository\PageRepository::class)->public(true);
    // Définir la classe concrète comme service public
    $services->set(TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class)->public(true);

    // Créer un alias : quand l'Interface est demandée, utiliser le service de la classe Concrète
    $services->alias(TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::class, TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class)->public(true);

    // --- Enregistrements Conditionnels ---
    $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

    if ($versionInformation->getMajorVersion() < 13) {
        // Services pour v12
        $services->set(LegacySemanticBackendController::class)
                 ->public(true) // Les contrôleurs doivent être publics
                 ->args([ // Définir explicitement les arguments injectés
                      service(TYPO3\CMS\Backend\Template\ModuleTemplateFactory::class),
                      service(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class),
                      service(TYPO3\CMS\Core\Log\LogManager::class),
                      service(TalanHdf\SemanticSuggestion\Service\LanguageService::class),
                      service(TYPO3\CMS\Core\Messaging\FlashMessageService::class)
                  ]);
        // Pas de tag backend_controller pour v12 ici

    } else {
        // Services pour v13+
        $services->set(SemanticBackendController::class)
                 ->public(true) // Les contrôleurs doivent être publics
                 ->tag('controller.backend_controller') // Tag pour v13
                 ->args([ // Définir explicitement les arguments injectés
                      service(TYPO3\CMS\Backend\Template\ModuleTemplateFactory::class),
                      service(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class),
                      service(TYPO3\CMS\Core\Log\LogManager::class),
                      service(TalanHdf\SemanticSuggestion\Service\LanguageService::class),
                      service(TYPO3\CMS\Core\Messaging\FlashMessageService::class)
                  ]);
    }

    // Configurer le logger
    $services->set('monolog.logger.semantic_suggestion', Logger::class)
        ->arg('$name', 'semantic_suggestion')
        ->call('pushHandler', [service('monolog.handler.semantic_suggestion')]);

    $services->set('monolog.handler.semantic_suggestion', RotatingFileHandler::class)
        ->arg('$filename', Environment::getVarPath() . '/log/semantic_suggestion.log')
        ->arg('$level', Level::Debug); // Utiliser l'enum/constante Monolog

     // Ajouter les calls setLogger pour les services qui implémentent LoggerAwareInterface
     // Assurez-vous que ces services implémentent bien l'interface
     $pageAnalysisServiceDef = $services->get(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class);
     if (is_a(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class, \Psr\Log\LoggerAwareInterface::class, true)) {
         $pageAnalysisServiceDef->call('setLogger', [service('monolog.logger.semantic_suggestion')]);
     }

     $languageServiceDef = $services->get(TalanHdf\SemanticSuggestion\Service\LanguageService::class);
     if (is_a(TalanHdf\SemanticSuggestion\Service\LanguageService::class, \Psr\Log\LoggerAwareInterface::class, true)) {
         $languageServiceDef->call('setLogger', [service('monolog.logger.semantic_suggestion')]);
     }
    // Ajoutez d'autres appels setLogger si nécessaire pour d'autres services
    $suggestionsControllerDef = $services->get(TalanHdf\SemanticSuggestion\Controller\SuggestionsController::class);
    if (is_a(TalanHdf\SemanticSuggestion\Controller\SuggestionsController::class, \Psr\Log\LoggerAwareInterface::class, true)) {
        $suggestionsControllerDef->call('setLogger', [service('monolog.logger.semantic_suggestion')]);
    }

};