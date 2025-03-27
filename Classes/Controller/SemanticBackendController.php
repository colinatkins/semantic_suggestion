<?php
namespace TalanHdf\SemanticSuggestion\Controller;

// --- Imports ---
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController; // Hériter directement pour v13
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Doctrine\DBAL\ParameterType;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TalanHdf\SemanticSuggestion\Service\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage; // Ajout pour addFlashMessage

class SemanticBackendController extends ActionController
{
    // --- PAS de redéclaration de $settings ou $arguments (propriétés héritées) ---

    // --- Propriétés pour l'Injection de Dépendances (DI) ---
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected PageAnalysisService $pageAnalysisService;
    protected ?PageRepository $pageRepository = null;
    protected LoggerInterface $logger;
    protected LanguageService $languageService;
    protected FlashMessageService $flashMessageService;

    // --- Constructeur pour DI (v13) ---
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

    // --- PAS de surcharge de initializeAction ou initializeValidators ici ---

    // --- Action Index (Logique métier principale) ---
    public function indexAction(): ResponseInterface
    {
        $this->logger->debug('Début de indexAction (v13 Controller)'); // Log spécifique v13
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        try {
            // Récupérer la configuration via le service PageAnalysisService
            $extensionConfig = $this->pageAnalysisService->getSettings();
            $parentPageId = (int)($extensionConfig['parentPageId'] ?? 1);
            $depth = (int)($extensionConfig['recursive'] ?? 1);
            $proximityThreshold = (float)($extensionConfig['proximityThreshold'] ?? 0.5);
            $excludePages = GeneralUtility::intExplode(',', $extensionConfig['excludePages'] ?? '', true);
            $maxSuggestions = (int)($extensionConfig['maxSuggestions'] ?? 5);
            $nlpEnabled = (bool)($extensionConfig['nlpEnabled'] ?? false);

            // Visibilité des sections
            $showStatistics = (bool)($extensionConfig['showStatistics'] ?? true);
            $showPerformanceMetrics = (bool)($extensionConfig['showPerformanceMetrics'] ?? true);
            $showLanguageStatistics = (bool)($extensionConfig['showLanguageStatistics'] ?? true);
            $showTopSimilarPairs = (bool)($extensionConfig['showTopSimilarPairs'] ?? true);
            $showDistributionScores = (bool)($extensionConfig['showDistributionScores'] ?? true);
            $showTopSimilarPages = (bool)($extensionConfig['showTopSimilarPages'] ?? true);

            $this->logger->debug('Using config (v13)', $extensionConfig); // Log spécifique v13
            $startTime = microtime(true);

            // Récupérer les langues du site
            $siteLanguages = [];
            try {
                $siteLanguages = $this->languageService->getSiteLanguages($parentPageId);
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
            $this->logger->warning('No site configuration found for page ID ' . $parentPageId, ['exception' => $e->getMessage()]);
                // Utiliser la méthode addFlashMessage de CE contrôleur (qui a la signature v13)
                $this->addFlashMessage('Site configuration not found for page ' . $parentPageId, 'Config Warning', ContextualFeedbackSeverity::WARNING);
                $siteLanguages = [];
            }

            // S'il n'y a pas de langues de site, traiter au moins la langue par défaut (0)
            if (empty($siteLanguages)) {
                $siteLanguages = [ GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\Entity\SiteLanguage::class, 0, 'default', new \Symfony\Component\HttpFoundation\ParameterBag(), $parentPageId) ];
                $this->logger->warning('No site languages found, processing default language 0 only.');
            }

            // Lire les données depuis la base pour toutes les langues disponibles
            $allLanguageData = [];
            $firstLanguageUidProcessed = null;

            foreach ($siteLanguages as $language) {
                $languageUid = $language->getLanguageId();
                if ($firstLanguageUidProcessed === null) {
                    $firstLanguageUidProcessed = $languageUid;
                }
                $dbData = $this->getAnalysisFromDatabase(
                    $parentPageId,
                    $depth,
                    $proximityThreshold,
                    $excludePages,
                    $languageUid
                );
                $allLanguageData[$languageUid] = $dbData;
            }

            // Fusionner ou sélectionner les données à afficher
            $mergedData = $this->mergeLanguageData($allLanguageData);

            $executionTime = microtime(true) - $startTime;

            // --- Préparation des métriques et statistiques (Nouvelle Logique) ---
            $statsLanguageUid = $firstLanguageUidProcessed ?? 0;

            $totalStoredSimilarities = 0;
            try {
                $countQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
                $totalStoredSimilarities = (int)$countQueryBuilder
                    ->count('uid')
                    ->from('tx_semanticsuggestion_similarities')
                    ->where(
                        $countQueryBuilder->expr()->eq('root_page_id', $countQueryBuilder->createNamedParameter($parentPageId, ParameterType::INTEGER)),
                        $countQueryBuilder->expr()->eq('sys_language_uid', $countQueryBuilder->createNamedParameter($statsLanguageUid, ParameterType::INTEGER)),
                        $countQueryBuilder->expr()->gte('similarity_score', $countQueryBuilder->createNamedParameter($proximityThreshold, ParameterType::STRING))
                    )
                    ->executeQuery()
                    ->fetchOne();
            } catch (\Exception $e) {
                $this->logger->error('Failed to count stored similarities (v13)', ['exception' => $e->getMessage()]); // Log spécifique v13
            }

            $performanceMetrics = [
                'executionTime' => $executionTime,
                'storedSimilarities' => $totalStoredSimilarities,
                'fromCache' => false,
            ];

            $totalValidatedPages = $mergedData['metrics']['totalPages'] ?? 0;

            // Récupérer les stats de langue
            $pageIdsInResults = array_keys($mergedData['results'] ?? []);
            $pagesForLangStats = [];
            if(!empty($pageIdsInResults)) {
                $pagesForLangStats = $this->getPageRepository()->getMenuForPages($pageIdsInResults, 'uid, sys_language_uid');
            }
            $languageStatistics = $this->languageService->getLanguageStatistics($pagesForLangStats, $siteLanguages);

            // Récupérer les messages Flash (VERSION V13)
            $flashMessages = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages')->getAllMessagesAndFlush();

            // Assigner les variables à la vue Fluid (Identique à la version Legacy)
            $moduleTemplate->assignMultiple([
                // Config
                'parentPageId' => $parentPageId, 'depth' => $depth, 'proximityThreshold' => $proximityThreshold,
                'excludePages' => implode(', ', $excludePages), 'maxSuggestions' => $maxSuggestions,

                // Visibilité
                'showStatistics' => $showStatistics, 'showPerformanceMetrics' => $showPerformanceMetrics,
                'showLanguageStatistics' => $showLanguageStatistics, 'showTopSimilarPairs' => $showTopSimilarPairs,
                'showDistributionScores' => $showDistributionScores, 'showTopSimilarPages' => $showTopSimilarPages,
                'nlpEnabled' => $nlpEnabled,

                // Données (Mises à jour)
                'performanceMetrics' => $showPerformanceMetrics ? $performanceMetrics : null,
                'statistics' => $showStatistics ? ($mergedData['statistics'] ?? null) : null,
                'analysisResults' => $mergedData['results'] ?? [],
                'languageStatistics' => $showLanguageStatistics ? ($languageStatistics['statistics'] ?? []) : null,
                'totalValidatedPages' => $totalValidatedPages,

                // Messages (Version v13)
                // Note: Le template doit être adapté si vous passez un tableau d'objets FlashMessage au lieu d'HTML rendu
                // Pour être sûr, rendons les messages en HTML ici aussi, même si getAllMessagesAndFlush est utilisé.
                // Ou adaptez le template pour boucler sur les objets $flashMessages.
                // Solution simple : Rendre les messages comme en v12
                // $flashMessagesRendered = '';
                // foreach ($flashMessages as $fm) { $flashMessagesRendered .= $fm->render(); }
                // 'flashMessages' => $flashMessagesRendered, // Passer le HTML rendu
                'flashMessages' => $flashMessages, // Passe le tableau d'objets (le template doit gérer ça)

            ]);

            // Rendre la réponse
            return $moduleTemplate->renderResponse('SemanticBackend/Index');

        } catch (\Exception $e) {
            $this->logger->error('Error in indexAction (v13)', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]); // Log spécifique v13
            // Utilisation de la méthode addFlashMessage de CE contrôleur (v13)
            $this->addFlashMessage('An error occurred (v13). Check logs: ' . $e->getMessage(), 'Error', ContextualFeedbackSeverity::ERROR);
            // Récupérer les messages pour les afficher même en cas d'erreur
            $flashMessages = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages')->getAllMessagesAndFlush();
            // Rendre les messages ici si besoin ou adapter le template
            $moduleTemplate->assignMultiple(['flashMessages' => $flashMessages, 'errorMessage' => 'An error occurred (v13). Check logs.']);
            return $moduleTemplate->renderResponse('SemanticBackend/Index');
        }
    } 

    // --- Méthodes Utilitaires (Identiques à Legacy) ---

    protected function getAnalysisFromDatabase(int $parentPageId, int $depth, float $proximityThreshold, array $excludePages, int $currentLanguageUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class) ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
        $similarities = $queryBuilder
            ->select('page_id', 'similar_page_id', 'similarity_score', 'root_page_id', 'sys_language_uid')
            ->from('tx_semanticsuggestion_similarities') ->where(
                $queryBuilder->expr()->eq('root_page_id', $queryBuilder->createNamedParameter($parentPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($currentLanguageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->gte('similarity_score', $queryBuilder->createNamedParameter($proximityThreshold, ParameterType::STRING))
            )->executeQuery()->fetchAllAssociative();
        $analysisResults = []; $pageIds = [];
        foreach ($similarities as $similarity) {
            $pageId = (int)$similarity['page_id']; $similarPageId = (int)$similarity['similar_page_id'];
            $pageIds[] = $pageId; $pageIds[] = $similarPageId;
            if (!isset($analysisResults[$pageId])) { $analysisResults[$pageId] = ['uid' => $pageId, 'similarities' => [], 'sys_language_uid' => (int)$similarity['sys_language_uid']]; }
            if (!in_array($similarPageId, $excludePages)) { $analysisResults[$pageId]['similarities'][$similarPageId] = ['score' => (float)$similarity['similarity_score'], 'relevance' => $this->determineRelevanceLevel((float)$similarity['similarity_score'])]; }
        }
        $pageIds = array_unique($pageIds); $pageDataRecords = [];
        if (!empty($pageIds)) {
            $pageDataRecords = $this->getPageRepository()->getMenuForPages($pageIds, 'uid, title', 'sorting', 'AND hidden=0 AND deleted=0');
            foreach ($analysisResults as $pageId => &$data) { $data['title'] = ['content' => $pageDataRecords[$pageId]['title'] ?? '[Page #' . $pageId . ']']; } unset($data);
            foreach ($pageIds as $pageId) { if (!isset($analysisResults[$pageId]) && isset($pageDataRecords[$pageId])) { $analysisResults[$pageId] = ['uid' => $pageId, 'title' => ['content' => $pageDataRecords[$pageId]['title'] ?? ''], 'similarities' => [], 'sys_language_uid' => $currentLanguageUid]; } }
        }
        $statistics = $this->calculateStatisticsFromDbResults($analysisResults);
        return ['results' => $analysisResults, 'statistics' => $statistics, 'metrics' => ['totalPages' => count($pageIds)]];
    }

    protected function mergeLanguageData(array $data): array { $firstKey = array_key_first($data); return $firstKey !== null ? $data[$firstKey] : ['results' => [], 'statistics' => [], 'metrics' => ['totalPages' => 0]]; }
    protected function calculateStatisticsFromDbResults(array $analysisResults): array { $stats = ['topSimilarPairs' => [], 'distributionScores' => [], 'topSimilarPages' => []]; $allPairs = []; $pageSimilarityCounts = []; $scoreRanges = ['0.0-0.2'=>0, '0.2-0.4'=>0, '0.4-0.6'=>0, '0.6-0.8'=>0, '0.8-1.0'=>0]; foreach ($analysisResults as $pageId => $data) { $pageSimilarityCounts[$pageId] = 0; foreach ($data['similarities'] as $similarPageId => $similarity) { $score = $similarity['score']; $allPairs[] = ['page1'=>$pageId, 'page2'=>$similarPageId, 'score'=>$score]; $pageSimilarityCounts[$pageId]++; if($score <= 0.2) $scoreRanges['0.0-0.2']++; elseif($score <= 0.4) $scoreRanges['0.2-0.4']++; elseif($score <= 0.6) $scoreRanges['0.4-0.6']++; elseif($score <= 0.8) $scoreRanges['0.6-0.8']++; else $scoreRanges['0.8-1.0']++; } } usort($allPairs, fn($a, $b)=>$b['score']<=>$a['score']); $uniquePairs = []; $displayedPairKeys = []; foreach($allPairs as $pair){ $key = min($pair['page1'], $pair['page2']).'-'.max($pair['page1'], $pair['page2']); if(!isset($displayedPairKeys[$key])){ $uniquePairs[] = $pair; $displayedPairKeys[$key] = true; } } $stats['topSimilarPairs'] = array_slice($uniquePairs, 0, 5); $stats['distributionScores'] = $scoreRanges; arsort($pageSimilarityCounts); $stats['topSimilarPages'] = array_slice($pageSimilarityCounts, 0, 5, true); return $stats; }
    protected function getPageRepository(): PageRepository { if ($this->pageRepository === null) { $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class); } return $this->pageRepository; }
    protected function determineRelevanceLevel(float $similarityScore): string { if ($similarityScore > 0.7) return 'High'; elseif ($similarityScore > 0.4) return 'Medium'; else return 'Low'; }
    public function addFlashMessage(
        string $messageBody, string $messageTitle = '',
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::INFO,
        bool $storeInSession = false
   ): void {
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $messageBody, $messageTitle, $severity, $storeInSession);
        $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages')->enqueue($flashMessage);
   }
} 