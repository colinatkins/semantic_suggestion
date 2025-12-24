<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TalanHdf\SemanticSuggestion\Controller\LegacySemanticBackendController;
use TalanHdf\SemanticSuggestion\Controller\SemanticBackendController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public(false);

    // Auto-register all classes in Classes/ except Controllers
    $services->load('TalanHdf\\SemanticSuggestion\\', '../Classes/*')
        ->exclude('../Classes/Controller/*');

    // --- Unconditional Service Registrations ---
    $services->set(TalanHdf\SemanticSuggestion\Controller\SuggestionsController::class)->public(true);
    $services->set(TalanHdf\SemanticSuggestion\Task\GenerateSimilaritiesTask::class)->public(true);
    $services->set(TalanHdf\SemanticSuggestion\Command\DiagnosticCommand::class)
        ->tag('console.command', ['command' => 'semantic:diagnostic']);

    $services->set(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class)->public(true);
    $services->set(TalanHdf\SemanticSuggestion\Service\SuggestionService::class)->public(true);
    $services->set(TalanHdf\SemanticSuggestion\Service\UtilityService::class)->public(true);
    $services->set(TalanHdf\SemanticSuggestion\Service\LanguageService::class);
    $services->set(TalanHdf\SemanticSuggestion\Service\SiteLanguageService::class);

    // Legacy DataHandler hook for TYPO3 12/13 (SC_OPTIONS)
    $services->set(TalanHdf\SemanticSuggestion\Hooks\DataHandlerHook::class);

    // TYPO3 14+ EventListener (replaces SC_OPTIONS hooks)
    // Uses PHP 8 attributes for auto-registration via autoconfigure
    $services->set(TalanHdf\SemanticSuggestion\EventListener\DataHandlerEventListener::class);

    // Optional nlp_tools services (auto-instantiated if available)
    if (class_exists(\Cywolf\NlpTools\Service\LanguageDetectionService::class)) {
        $services->set(\Cywolf\NlpTools\Service\LanguageDetectionService::class)->public(true);
        $services->set(\Cywolf\NlpTools\Service\TextAnalysisService::class)->public(true);
        $services->set(\Cywolf\NlpTools\Service\TextVectorizerService::class)->public(true);
        $services->set(\Cywolf\NlpTools\Service\StopWordsFactory::class)->public(true);
    }

    // Make required Core services public for injection
    $services->set(TYPO3\CMS\Core\Log\LogManager::class)->public(true);
    $services->set(TYPO3\CMS\Core\Messaging\FlashMessageService::class)->public(true);
    $services->set(TYPO3\CMS\Backend\Template\ModuleTemplateFactory::class)->public(true);
    $services->set(TYPO3\CMS\Core\Context\Context::class)->public(true);
    $services->set(TYPO3\CMS\Core\Site\SiteFinder::class)->public(true);
    $services->set(TYPO3\CMS\Core\Resource\FileRepository::class)->public(true);
    $services->set(TYPO3\CMS\Core\Domain\Repository\PageRepository::class)->public(true);
    $services->set(TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class)->public(true);
    $services->alias(
        TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::class,
        TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class
    )->public(true);
    $services->set(TYPO3\CMS\Core\Database\ConnectionPool::class)->public(true);

    // --- Version-Conditional Controller Registration ---
    $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

    if ($versionInformation->getMajorVersion() < 13) {
        // TYPO3 v12: Use Legacy controller
        $services->set(LegacySemanticBackendController::class)
            ->public(true)
            ->tag('controller.backend_controller')
            ->args([
                service(TYPO3\CMS\Backend\Template\ModuleTemplateFactory::class),
                service(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class),
                service(TYPO3\CMS\Core\Log\LogManager::class),
                service(TalanHdf\SemanticSuggestion\Service\LanguageService::class),
                service(TYPO3\CMS\Core\Messaging\FlashMessageService::class),
                service(TYPO3\CMS\Core\Domain\Repository\PageRepository::class),
                service(TYPO3\CMS\Core\Database\ConnectionPool::class),
            ]);
    } else {
        // TYPO3 v13+/v14: Use modern controller
        $services->set(SemanticBackendController::class)
            ->public(true)
            ->tag('controller.backend_controller')
            ->args([
                service(TYPO3\CMS\Backend\Template\ModuleTemplateFactory::class),
                service(TalanHdf\SemanticSuggestion\Service\PageAnalysisService::class),
                service(TYPO3\CMS\Core\Log\LogManager::class),
                service(TalanHdf\SemanticSuggestion\Service\LanguageService::class),
                service(TYPO3\CMS\Core\Messaging\FlashMessageService::class),
                service(TYPO3\CMS\Core\Domain\Repository\PageRepository::class),
                service(TYPO3\CMS\Core\Database\ConnectionPool::class),
            ]);
    }
};
