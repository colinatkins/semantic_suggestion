<?php
namespace TalanHdf\SemanticSuggestion\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TalanHdf\SemanticSuggestion\Service\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Doctrine\DBAL\ParameterType; 

class SemanticBackendController extends AbstractCompatibleController
{
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected PageAnalysisService $pageAnalysisService;
    protected ?PageRepository $pageRepository = null;
    protected ?FrontendInterface $cache = null;
    protected ExtensionConfiguration $extensionConfiguration;
    protected ?CacheManager $cacheManager = null;
    protected LoggerInterface $logger;
    protected LanguageService $languageService;
    protected FlashMessageService $flashMessageService;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        PageAnalysisService $pageAnalysisService,
        LogManager $logManager,
        LanguageService $languageService,
        FlashMessageService $flashMessageService
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->pageAnalysisService = $pageAnalysisService;
        $this->logger = $logManager->getLogger(__CLASS__);
        $this->languageService = $languageService;
        $this->flashMessageService = $flashMessageService;
    }


    protected $templateRootPaths = [];

    public function setTemplateRootPaths(array $templateRootPaths): void
    {
        $this->templateRootPaths = $templateRootPaths;
    }
    
    protected function initializeView(): void
    {
        if ($this->view !== null && !empty($this->templateRootPaths)) {
            try {
                $this->view->setTemplateRootPaths($this->templateRootPaths);
            } catch (\Exception $e) {
                $this->logger->debug('Could not set template root paths', [
                    'exception' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Initializes the object after all dependencies are injected
     * Called by TYPO3's Dependency Injection Container
     */
    public function initializeObject(): void
    {
        if ($this->cacheManager === null) {
            $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        }
    }

    public function initializeAction(): void
    {
        parent::initializeAction();
        
        // Essayez de charger les settings après l'initialisation par le parent
        if (is_object($this->configurationManager)) {
            $extensionConfig = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            if (isset($extensionConfig['plugin.']['tx_semanticsuggestion_suggestions.']['settings.'])) {
                $this->settings = $extensionConfig['plugin.']['tx_semanticsuggestion_suggestions.']['settings.'] ?? [];
                $this->pageAnalysisService->setSettings($this->settings);
            }
        }
    }

    public function injectCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    protected function getCache(): FrontendInterface
    {
        if ($this->cache === null) {
            if ($this->cacheManager === null) {
                $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            }
            $this->cache = $this->cacheManager->getCache('semantic_suggestion');
        }
        return $this->cache;
    }

    private function logDebug(string $message, array $context = []): void
    {
        $debugMode = $this->pageAnalysisService instanceof PageAnalysisService 
            ? ($this->pageAnalysisService->getSettings()['debugMode'] ?? false)
            : false;
    
        if ($debugMode && $this->logger instanceof LoggerInterface) {
            $this->logger->debug($message, $context);
        }
    }

    public function updateConfigurationAction(array $configuration): ResponseInterface
    {
        // Update the extension configuration
        $this->extensionConfiguration->set('semantic_suggestion', $configuration);

        // Update TypoScript configuration
        $fullTypoScript = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        $pluginSettings = &$fullTypoScript['plugin.']['tx_semanticsuggestion_suggestions.']['settings.'];
        foreach ($configuration as $key => $value) {
            $pluginSettings[$key] = $value;
        }
        $this->configurationManager->setConfiguration($fullTypoScript);

        // Add a flash message to confirm the update
        $this->addFlashMessage(
            'The configuration has been updated successfully.',
            'Configuration Updated',
            ContextualFeedbackSeverity::OK
        );
        
        // Redirect back to the index action
        return $this->redirect('index');
    }

    public function injectExtensionConfiguration(ExtensionConfiguration $extensionConfiguration): void
    {
        $this->extensionConfiguration = $extensionConfiguration;
    }

    public function injectModuleTemplateFactory(ModuleTemplateFactory $moduleTemplateFactory): void
    {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->logDebug('ModuleTemplateFactory injected');
    }

    public function injectPageAnalysisService(PageAnalysisService $pageAnalysisService): void
    {
        $this->pageAnalysisService = $pageAnalysisService;
        if ($this->logger) {
            $this->logger->debug('PageAnalysisService settings', ['settings' => $this->pageAnalysisService->getSettings()]);
        }
    }

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }

        
    public function indexAction(): ResponseInterface
    {
        $this->logDebug('Début de indexAction');
        $mergedData = [];
        
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        
        try {
            $fullTypoScript = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            $extensionConfig = $fullTypoScript['plugin.']['tx_semanticsuggestion_suggestions.']['settings.'] ?? [];
            $this->pageAnalysisService->setSettings($extensionConfig);
            $this->logDebug('Debug mode in controller', ['debugMode' => $this->pageAnalysisService->getSettings()['debugMode']]);
        
            $parentPageId = (int)($extensionConfig['parentPageId'] ?? 0);
            $depth = (int)($extensionConfig['recursive'] ?? 1);
            $proximityThreshold = (float)($extensionConfig['proximityThreshold'] ?? 0.5);
            $maxSuggestions = (int)($extensionConfig['maxSuggestions'] ?? 5);
            $excludePages = GeneralUtility::intExplode(',', $extensionConfig['excludePages'] ?? '', true);
            $recencyWeight = (float)($extensionConfig['recencyWeight'] ?? 0.2);
            $showStatistics = (bool)($extensionConfig['showStatistics'] ?? true);
            $showPerformanceMetrics = (bool)($extensionConfig['showPerformanceMetrics'] ?? true);
            $showLanguageStatistics = (bool)($extensionConfig['showLanguageStatistics'] ?? true);
            $calculateDistribution = (bool)($extensionConfig['calculateDistribution'] ?? true);
            $calculateTopSimilarPairs = (bool)($extensionConfig['calculateTopSimilarPairs'] ?? true);
        
            $startTime = microtime(true);
        
            try {
                $siteLanguages = $this->languageService->getSiteLanguages($parentPageId);
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
                $this->logger->warning('No site configuration found for page ID ' . $parentPageId, ['exception' => $e->getMessage()]);
                $this->addFlashMessage(
                    'No site configuration found for page ID ' . $parentPageId . '. Please configure a site in the TYPO3 backend.',
                    'Site Configuration Missing',
                    ContextualFeedbackSeverity::WARNING
                );
                $siteLanguages = [];
            }
        
            $allPages = $this->getPages($parentPageId, $depth);
            $totalPagesAnalyzed = count($allPages);
        
            $validatedPages = array_filter($allPages, function($page) use ($excludePages) {
                return !in_array($page['uid'], $excludePages);
            });
            $totalValidatedPages = count($validatedPages);
        
            $languageStatistics = $this->languageService->getLanguageStatistics($validatedPages, $siteLanguages);
        
            $this->logDebug('Pages summary', [
                'totalCount' => $totalPagesAnalyzed,
                'validatedCount' => $totalValidatedPages,
                'pagesByLanguage' => $languageStatistics
            ]);
        
            $data = [];
            foreach ($siteLanguages as $language) {
                $languageUid = $language->getLanguageId();
                $cacheIdentifier = $this->generateValidCacheIdentifier($parentPageId, $depth, $proximityThreshold, $maxSuggestions, $languageUid);
        
                if ($this->getCache()->has($cacheIdentifier)) {
                    $languageData = $this->getCache()->get($cacheIdentifier);
                } else {
                    $languageData = $this->getAnalysisFromDatabase(
                        $parentPageId,
                        $depth,
                        $proximityThreshold,
                        $excludePages,
                        $languageUid
                    );
                    $this->getCache()->set($cacheIdentifier, $languageData, ['semantic_suggestion'], 3600);
                }
                $data[$languageUid] = $languageData;
            }
        
            $mergedData = $this->mergeLanguageData($data);
            $executionTime = microtime(true) - $startTime;
        
            $this->logDebug('Analysis summary', [
                'totalPagesAnalyzed' => $totalPagesAnalyzed,
                'totalValidatedPages' => $totalValidatedPages,
                'executionTime' => $executionTime
            ]);
        
            $performanceMetrics = [
                'executionTime' => $executionTime,
                'totalPagesAnalyzed' => $totalPagesAnalyzed,
                'totalValidatedPages' => $totalValidatedPages,
                'similarityCalculations' => $totalValidatedPages * ($totalValidatedPages - 1) / 2,
                'fromCache' => 'Yes'
            ];
        
            if (isset($mergedData['statistics']['topSimilarPairs'])) {
                $uniquePairs = [];
                foreach ($mergedData['statistics']['topSimilarPairs'] as $pair) {
                    $key = min($pair['page1'], $pair['page2']) . '-' . max($pair['page1'], $pair['page2']);
                    if (!isset($uniquePairs[$key])) {
                        $uniquePairs[$key] = $pair;
                    }
                }
                $mergedData['statistics']['topSimilarPairs'] = array_values($uniquePairs);
                $this->logDebug('Top Similar Pairs after deduplication', [
                    'pairs' => $mergedData['statistics']['topSimilarPairs']
                ]);
            }
        
            // Utiliser FlashMessageService pour récupérer les messages flash
            $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages');
            $flashMessages = $flashMessageQueue->renderFlashMessages();
        
            $moduleTemplate->assignMultiple([
                'parentPageId' => $parentPageId,
                'depth' => $depth,
                'proximityThreshold' => $proximityThreshold,
                'maxSuggestions' => $maxSuggestions,
                'excludePages' => implode(', ', $excludePages),
                'recencyWeight' => $recencyWeight,
                'performanceMetrics' => $showPerformanceMetrics ? $performanceMetrics : null,
                'showStatistics' => $showStatistics,
                'showPerformanceMetrics' => $showPerformanceMetrics,
                'showLanguageStatistics' => $showLanguageStatistics,
                'showTopSimilarPairs' => (bool)($extensionConfig['showTopSimilarPairs'] ?? true),
                'showDistributionScores' => (bool)($extensionConfig['showDistributionScores'] ?? true),
                'showTopSimilarPages' => (bool)($extensionConfig['showTopSimilarPages'] ?? true),
                'statistics' => $showStatistics ? ($mergedData['statistics'] ?? null) : null,
                'analysisResults' => $mergedData['analysisResults'] ?? [],
                'totalPagesAnalyzed' => $totalPagesAnalyzed,
                'totalValidatedPages' => $totalValidatedPages,
                'languageStatistics' => $languageStatistics['statistics'],
                'flashMessages' => $flashMessages,
            ]);
        
            return $moduleTemplate->renderResponse('SemanticBackend/Index');
        
        } catch (\Exception $e) {
            $this->logger->error('Error in indexAction', ['exception' => $e->getMessage()]);
            $this->addFlashMessage(
                'An error occurred while processing the data. Please check the logs for more information.',
                'Error',
                ContextualFeedbackSeverity::ERROR
            );
        
            // Récupérer les messages flash en cas d’erreur
            $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages');
            $flashMessages = $flashMessageQueue->renderFlashMessages();
        
            $moduleTemplate->assignMultiple([
                'flashMessages' => $flashMessages,
                'errorMessage' => 'An error occurred. Check logs for details.',
            ]);
            return $moduleTemplate->renderResponse('SemanticBackend/Index');
        }
        
        $this->logDebug('Fin de indexAction');
    }

    protected function getAnalysisFromDatabase(int $parentPageId, int $depth, float $proximityThreshold, array $excludePages, int $currentLanguageUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');

        // Récupérer SEULEMENT les colonnes nécessaires explicitement
        $similarities = $queryBuilder
            ->select( // Colonnes sélectionnées explicitement
                'page_id',
                'similar_page_id',
                'similarity_score',
                'root_page_id',
                'sys_language_uid'
                // Note: 'uid', 'crdate', 'tstamp' ne sont pas sélectionnées ici car elles ne semblent pas
                // utilisées directement dans le reste de la méthode pour construire analysisResults.
                // Si vous en avez besoin, ajoutez-les à cette liste.
            )
            ->from('tx_semanticsuggestion_similarities')
            ->where(
                $queryBuilder->expr()->eq('root_page_id', $queryBuilder->createNamedParameter($parentPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($currentLanguageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->gte('similarity_score', $queryBuilder->createNamedParameter($proximityThreshold, ParameterType::STRING))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        // Préparer la structure de données
        $analysisResults = [];
        $pageIds = []; // Pour récupérer les titres des pages plus tard

        foreach ($similarities as $similarity) {
            // Collecter les IDs des pages concernées
            $pageIds[] = (int)$similarity['page_id'];
            $pageIds[] = (int)$similarity['similar_page_id'];

            // Initialiser la structure pour la page source si elle n'existe pas
            if (!isset($analysisResults[$similarity['page_id']])) {
                $analysisResults[$similarity['page_id']] = [
                    'uid' => (int)$similarity['page_id'], // Ajout de l'UID pour la cohérence
                    'similarities' => [],
                    'sys_language_uid' => (int)$similarity['sys_language_uid'] // Utiliser la valeur de la table
                ];
            }

            // Ne pas inclure les pages exclues dans les suggestions
            if (!in_array((int)$similarity['similar_page_id'], $excludePages)) {
                $analysisResults[$similarity['page_id']]['similarities'][(int)$similarity['similar_page_id']] = [
                    'score' => (float)$similarity['similarity_score'],
                    'relevance' => $this->determineRelevanceLevel((float)$similarity['similarity_score'])
                    // Note: 'commonKeywords', 'ageInDays', etc. ne sont pas disponibles
                    // car non stockés dans tx_semanticsuggestion_similarities.
                    // Seules les données brutes de similarité sont récupérées ici.
                ];
            }
        }

        // Récupérer les informations des pages concernées (titres principalement)
        $pageIds = array_unique($pageIds);
        if (!empty($pageIds)) {
            // Utiliser getPageRepository() pour s'assurer qu'il est initialisé
            $pageDataRecords = $this->getPageRepository()
                ->getMenuForPages($pageIds, 'uid, title', 'sorting', 'AND hidden=0 AND deleted=0');

            // Ajouter les titres aux résultats d'analyse
            foreach ($analysisResults as $pageId => &$data) {
                if (isset($pageDataRecords[$pageId])) {
                    $data['title'] = ['content' => $pageDataRecords[$pageId]['title'] ?? ''];
                    // Ajouter d'autres champs de base si nécessaire et si récupérés par getMenuForPages
                } else {
                     $data['title'] = ['content' => '[Page non trouvée ou inaccessible: ' . $pageId . ']'];
                }
            }
            unset($data); // Détruire la référence pour éviter les effets de bord

            // S'assurer que toutes les pages mentionnées dans 'similarities' ont aussi une entrée
            // de premier niveau dans analysisResults, même si elles n'ont pas de similarités sortantes.
             foreach ($pageIds as $pageId) {
                 if (!isset($analysisResults[$pageId]) && isset($pageDataRecords[$pageId])) {
                      $analysisResults[$pageId] = [
                          'uid' => $pageId,
                          'title' => ['content' => $pageDataRecords[$pageId]['title'] ?? ''],
                          'similarities' => [],
                           'sys_language_uid' => $currentLanguageUid // Hypothèse, peut nécessiter ajustement
                      ];
                 }
             }
        }

        // Retourner les données formatées comme attendu par la méthode appelante (indexAction)
        return [
            'results' => $analysisResults,
            'metrics' => [
                'executionTime' => 0, // Temps de calcul direct négligeable (lecture DB)
                'totalPages' => count($pageIds), // Nombre total de pages uniques impliquées
                'similarityCalculations' => 0, // Les calculs ont été faits par la tâche Scheduler
                'fromCache' => false, // Données lues depuis la DB, pas le cache d'analyse
            ],
        ];
    }

    protected function getPageRepository(): PageRepository
    {
        if ($this->pageRepository === null) {
            $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        }
        return $this->pageRepository;
    }

    /**
     * Détermine le niveau de pertinence basé sur un score de similarité
     */
    protected function determineRelevanceLevel(float $similarityScore): string
    {
        if ($similarityScore > 0.7) {
            return 'High';
        } elseif ($similarityScore > 0.4) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }
    



    protected function mergeLanguageData(array $data): array
    {
        $mergedData = [
            'statistics' => [],
            'analysisResults' => [],
            'languageStatistics' => [],
            'totalPages' => 0,
        ];

        if (empty($data)) {
            return $mergedData;
        }

        $allPageUids = [];
        foreach ($data as $languageUid => $languageData) {
            // Vérifier que analysisResults existe et est un array
            if (isset($languageData['analysisResults']) && is_array($languageData['analysisResults'])) {
                $allPageUids = array_merge($allPageUids, array_keys($languageData['analysisResults']));
                $mergedData['analysisResults'] = array_merge(
                    $mergedData['analysisResults'],
                    $languageData['analysisResults']
                );
            }

            // Vérifier que languageStatistics existe et est un array
            if (isset($languageData['languageStatistics']) && is_array($languageData['languageStatistics'])) {
                $mergedData['languageStatistics'] = array_merge(
                    $mergedData['languageStatistics'],
                    $languageData['languageStatistics']
                );
            }

            // Vérifier que totalPages existe
            if (isset($languageData['totalPages'])) {
                $mergedData['totalPages'] += (int)$languageData['totalPages'];
            }
        }

        // Fusionner les statistiques
        $statisticsArray = array_column($data, 'statistics');
        $statisticsArray = array_filter($statisticsArray, function($stats) {
            return is_array($stats);
        });

        if (!empty($statisticsArray)) {
            $mergedData['statistics'] = $this->mergeStatistics($statisticsArray);
        }

        $this->logDebug('Merged language data', [
            'totalPages' => $mergedData['totalPages'],
            'pagesCount' => count($mergedData['analysisResults']),
            'languagesCount' => count($data)
        ]);

        return $mergedData;
    }
    
    protected function mergeStatistics(array $statisticsArray): array
    {
        $mergedStats = [
            'totalPages' => 0,
            'averageSimilarity' => 0,
            'topSimilarPairs' => [],
            'distributionScores' => [],
            'topSimilarPages' => [],
        ];
    
        $totalSimilarityScore = 0;
        $totalPairs = 0;
    
        foreach ($statisticsArray as $stats) {
            $mergedStats['totalPages'] += $stats['totalPages'];
            $totalSimilarityScore += $stats['averageSimilarity'] * $stats['totalPages'] * ($stats['totalPages'] - 1) / 2;
            $totalPairs += $stats['totalPages'] * ($stats['totalPages'] - 1) / 2;
    
            $mergedStats['topSimilarPairs'] = array_merge($mergedStats['topSimilarPairs'], $stats['topSimilarPairs'] ?? []);
            $mergedStats['topSimilarPages'] += $stats['topSimilarPages'] ?? [];
    
            foreach ($stats['distributionScores'] ?? [] as $range => $count) {
                $mergedStats['distributionScores'][$range] = ($mergedStats['distributionScores'][$range] ?? 0) + $count;
            }
        }
    
        $mergedStats['averageSimilarity'] = $totalPairs > 0 ? $totalSimilarityScore / $totalPairs : 0;
    
        usort($mergedStats['topSimilarPairs'], function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $mergedStats['topSimilarPairs'] = array_slice($mergedStats['topSimilarPairs'], 0, 5);
    
        arsort($mergedStats['topSimilarPages']);
        $mergedStats['topSimilarPages'] = array_slice($mergedStats['topSimilarPages'], 0, 5, true);
    
        return $mergedStats;
    }



    protected function getPages(int $parentPageId, int $depth): array
    {
        $pageRepository = $this->getPageRepository();
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($parentPageId);
        $allPages = [];
    
        $defaultLanguagePages = $pageRepository->getMenu($parentPageId, '*', 'sorting', 'AND hidden=0 AND deleted=0', false);
        
        foreach ($defaultLanguagePages as $page) {
            $allPages[$page['uid']] = $page;
            $allPages[$page['uid']]['sys_language_uid'] = 0;
            
            foreach ($site->getLanguages() as $siteLanguage) {
                $languageId = $siteLanguage->getLanguageId();
                if ($languageId > 0) {
                    $localizedPage = $pageRepository->getPageOverlay($page, $languageId);
                    if ($localizedPage && isset($localizedPage['_PAGES_OVERLAY_UID'])) {
                        $localizedPage['sys_language_uid'] = $languageId;
                        $allPages[$localizedPage['_PAGES_OVERLAY_UID']] = $localizedPage;
                    }
                }
            }
    
            if ($depth > 1) {
                $subPages = $this->getPages($page['uid'], $depth - 1);
                $allPages = array_merge($allPages, $subPages);
            }
        }
    
        return $allPages;
    }

    
    protected function getPageTranslations(int $pageUid): array
    {
        $translations = [];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageUid);
    
        foreach ($this->languageService->getAllLanguages() as $language) {
            $languageId = $language->getLanguageId();
            if ($languageId > 0) {  // Only for non-default languages
                $translatedPage = $this->pageRepository->getPageOverlay($pageUid, $languageId);
                if ($translatedPage && isset($translatedPage['uid'])) {
                    $translatedPage['sys_language_uid'] = $languageId;
                    $translations[$languageId] = $translatedPage;
                }
            }
        }
    
        return $translations;
    }

    protected function generateValidCacheIdentifier(int $parentPageId, int $depth, float $proximityThreshold, int $maxSuggestions, int $currentLanguageUid): string
    {
        $identifier = 'semantic_analysis_' . $parentPageId . '_' . $depth . '_' . $proximityThreshold . '_' . $maxSuggestions . '_' . $currentLanguageUid;
        return md5($identifier);
    }

    protected function getCurrentPageId(): int
    {
        $pageId = (int)($GLOBALS['TSFE']->id ?? $this->request->getQueryParams()['id'] ?? 0);
        
        if ($pageId === 0) {
            // Si nous n'avons pas d'ID de page, essayons de prendre le premier site disponible
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $sites = $siteFinder->getAllSites();
            if (!empty($sites)) {
                $firstSite = reset($sites);
                $pageId = $firstSite->getRootPageId();
            }
        }
        
        if ($pageId === 0) {
            throw new \RuntimeException('No valid page ID found', 1631234567);
        }
        
        return $pageId;
    }
}