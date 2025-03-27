<?php
namespace TalanHdf\SemanticSuggestion\Controller;

// --- Imports utilisés ---
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TalanHdf\SemanticSuggestion\Service\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController; // Héritage direct
use TYPO3\CMS\Core\Messaging\FlashMessage; // Pour addFlashMessage

// --- Imports potentiellement non utilisés après nettoyage (à vérifier) ---
// use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
// use TYPO3\CMS\Core\Site\SiteFinder;
// use TYPO3\CMS\Core\Cache\CacheManager;
// use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
// use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
// use TYPO3\CMS\Core\Context\Context;


class LegacySemanticBackendController extends ActionController
{
    // --- Propriétés injectées via Constructeur ---
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected PageAnalysisService $pageAnalysisService;
    protected LoggerInterface $logger;
    protected LanguageService $languageService;
    protected FlashMessageService $flashMessageService;

    // --- Autres propriétés ---
    protected ?PageRepository $pageRepository = null; // Initialisé dans getPageRepository

    // --- PAS de déclaration $settings, $arguments, $templateRootPaths ---

    // --- Constructeur ---
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

    // --- PAS de initializeAction ou initializeValidators ici ---
    // --- PAS de setTemplateRootPaths ou initializeView ici ---
    // --- PAS de initializeObject ou getCache ici ---
    // --- PAS de méthodes inject* ici (l'injection se fait via constructeur ou @inject) ---

    // --- Méthode pour le logging (simplifiée) ---
    private function logDebug(string $message, array $context = []): void
    {
        // Note: Pour récupérer le debugMode, il faudrait passer par $this->pageAnalysisService->getSettings()
        // ou réinjecter ConfigurationManager si nécessaire. Simplifions pour l'instant.
        // if ($debugMode) {
             $this->logger->debug($message, $context);
        // }
    }

   // --- Action Index (Logique métier principale) ---
    public function indexAction(): ResponseInterface
    {
        $this->logDebug('Début de indexAction (Legacy Controller)');
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        try {
            // Récupérer la configuration via le service PageAnalysisService
            $extensionConfig = $this->pageAnalysisService->getSettings();
            $parentPageId = (int)($extensionConfig['parentPageId'] ?? 1);
            $depth = (int)($extensionConfig['recursive'] ?? 1); // Note: 'depth' est lié à la config Scheduler, peut être affiché à titre indicatif
            $proximityThreshold = (float)($extensionConfig['proximityThreshold'] ?? 0.5); // Seuil pour l'affichage
            $excludePages = GeneralUtility::intExplode(',', $extensionConfig['excludePages'] ?? '', true);
            $maxSuggestions = (int)($extensionConfig['maxSuggestions'] ?? 5); // Pour info frontend
            $nlpEnabled = (bool)($extensionConfig['nlpEnabled'] ?? false); // Si vous avez une config NLP

            // Visibilité des sections
            $showStatistics = (bool)($extensionConfig['showStatistics'] ?? true);
            $showPerformanceMetrics = (bool)($extensionConfig['showPerformanceMetrics'] ?? true);
            $showLanguageStatistics = (bool)($extensionConfig['showLanguageStatistics'] ?? true);
            $showTopSimilarPairs = (bool)($extensionConfig['showTopSimilarPairs'] ?? true);
            $showDistributionScores = (bool)($extensionConfig['showDistributionScores'] ?? true);
            $showTopSimilarPages = (bool)($extensionConfig['showTopSimilarPages'] ?? true);

            $this->logDebug('Using config (Legacy)', $extensionConfig);
            $startTime = microtime(true);

            // Récupérer les langues du site
            $siteLanguages = [];
            try {
                $siteLanguages = $this->languageService->getSiteLanguages($parentPageId);
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
                $this->logger->warning('No site configuration found for page ID ' . $parentPageId, ['exception' => $e->getMessage()]);
                $this->addFlashMessage('Site configuration not found for page ' . $parentPageId, 'Config Warning', ContextualFeedbackSeverity::WARNING);
                $siteLanguages = [];
            }

            // S'il n'y a pas de langues de site, traiter au moins la langue par défaut (0)
            if (empty($siteLanguages)) {
                    // Création d'un objet langue par défaut minimaliste
                    $siteLanguages = [ GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\Entity\SiteLanguage::class, 0, 'default', new \Symfony\Component\HttpFoundation\ParameterBag(), $parentPageId) ];
                    $this->logger->warning('No site languages found, processing default language 0 only.');
            }

            // Lire les données depuis la base pour toutes les langues disponibles
            $allLanguageData = [];
            // $totalDbPages = 0; // Cette variable n'est plus utilisée directement
            $firstLanguageUidProcessed = null; // Garder trace de la première langue traitée

            foreach ($siteLanguages as $language) {
                $languageUid = $language->getLanguageId();
                if ($firstLanguageUidProcessed === null) {
                    $firstLanguageUidProcessed = $languageUid; // Stocke l'ID de la première langue
                }
                // Lire les données depuis la DB pour cette langue
                $dbData = $this->getAnalysisFromDatabase(
                    $parentPageId, // Utiliser comme root_page_id
                    $depth, // Gardé pour contexte, mais non utilisé dans la requête DB
                    $proximityThreshold,
                    $excludePages,
                    $languageUid
                );
                $allLanguageData[$languageUid] = $dbData;
                // $totalDbPages += $dbData['metrics']['totalPages'] ?? 0; // Calcul fait différemment maintenant
            }

            // Fusionner ou sélectionner les données à afficher
            // NOTE: Votre mergeLanguageData actuel prend juste les données de la première langue.
            // Si vous voulez des stats agrégées, cette méthode doit être revue.
            // Pour l'instant, on se base sur la logique existante (première langue).
            $mergedData = $this->mergeLanguageData($allLanguageData);

            $executionTime = microtime(true) - $startTime;

            // --- Préparation des métriques et statistiques (Nouvelle Logique) ---

            // Langue pour laquelle les statistiques affichées sont valides (basé sur mergeLanguageData)
            $statsLanguageUid = $firstLanguageUidProcessed ?? 0; // Utilise la première langue traitée ou 0 par défaut

            $totalStoredSimilarities = 0;
            try {
                $countQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
                $totalStoredSimilarities = (int)$countQueryBuilder
                    ->count('uid')
                    ->from('tx_semanticsuggestion_similarities')
                    ->where(
                            $countQueryBuilder->expr()->eq('root_page_id', $countQueryBuilder->createNamedParameter($parentPageId, ParameterType::INTEGER)),
                            $countQueryBuilder->expr()->eq('sys_language_uid', $countQueryBuilder->createNamedParameter($statsLanguageUid, ParameterType::INTEGER)), // Utilise la langue déterminée
                            $countQueryBuilder->expr()->gte('similarity_score', $countQueryBuilder->createNamedParameter($proximityThreshold, ParameterType::STRING)) // Compte au-dessus du seuil d'affichage
                    )
                    ->executeQuery()
                    ->fetchOne();
            } catch (\Exception $e) {
                    $this->logger->error('Failed to count stored similarities (Legacy)', ['exception' => $e->getMessage()]);
            }

            $performanceMetrics = [
                'executionTime' => $executionTime, // Temps chargement module
                'storedSimilarities' => $totalStoredSimilarities, // Nombre total de paires stockées > seuil
                'fromCache' => false, // Toujours faux
            ];

            // Nombre de pages uniques DANS les résultats affichés (celles avec au moins 1 similarité > seuil)
            $totalValidatedPages = $mergedData['metrics']['totalPages'] ?? 0;

            // Récupérer les stats de langue basées sur les pages dans les résultats
            $pageIdsInResults = array_keys($mergedData['results'] ?? []);
            $pagesForLangStats = [];
            if(!empty($pageIdsInResults)) {
                // Assurez-vous que getMenuForPages récupère bien le sys_language_uid
                $pagesForLangStats = $this->getPageRepository()->getMenuForPages($pageIdsInResults, 'uid, sys_language_uid');
            }
                // Recalcule les stats de langue basées sur les pages effectivement trouvées dans $mergedData
            $languageStatistics = $this->languageService->getLanguageStatistics($pagesForLangStats, $siteLanguages);


            // Récupérer et préparer les messages Flash (V12)
            $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages');
            $flashMessages = $flashMessageQueue->renderFlashMessages();

            // Assigner les variables à la vue Fluid (VERSION MISE À JOUR)
            $moduleTemplate->assignMultiple([
                // Config
                'parentPageId' => $parentPageId,
                'depth' => $depth,
                'proximityThreshold' => $proximityThreshold,
                'excludePages' => implode(', ', $excludePages),
                'maxSuggestions' => $maxSuggestions,

                // Visibilité
                'showStatistics' => $showStatistics, 'showPerformanceMetrics' => $showPerformanceMetrics,
                'showLanguageStatistics' => $showLanguageStatistics, 'showTopSimilarPairs' => $showTopSimilarPairs,
                'showDistributionScores' => $showDistributionScores, 'showTopSimilarPages' => $showTopSimilarPages,
                'nlpEnabled' => $nlpEnabled,

                // Données (Mises à jour)
                'performanceMetrics' => $showPerformanceMetrics ? $performanceMetrics : null,
                'statistics' => $showStatistics ? ($mergedData['statistics'] ?? null) : null,
                'analysisResults' => $mergedData['results'] ?? [],
                    // Assurez-vous que la clé est bien 'statistics' ici
                'languageStatistics' => $showLanguageStatistics ? ($languageStatistics['statistics'] ?? []) : null,
                'totalValidatedPages' => $totalValidatedPages,

                // Messages
                'flashMessages' => $flashMessages,
            ]);

            // Rendre la réponse
            return $moduleTemplate->renderResponse('SemanticBackend/Index');

        } catch (\Exception $e) {
                $this->logger->error('Error in indexAction (Legacy)', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                // Utilisation de la méthode addFlashMessage définie dans la classe
                $this->addFlashMessage('An error occurred (Legacy). Check logs: ' . $e->getMessage(), 'Error', ContextualFeedbackSeverity::ERROR);
                $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages');
                $flashMessages = $flashMessageQueue->renderFlashMessages(); // Pour V12
                $moduleTemplate->assignMultiple(['flashMessages' => $flashMessages, 'errorMessage' => 'An error occurred (Legacy). Check logs.']);
                return $moduleTemplate->renderResponse('SemanticBackend/Index');
        }
    } 

    protected function getAnalysisFromDatabase(int $parentPageId, int $depth, float $proximityThreshold, array $excludePages, int $currentLanguageUid): array
    {
        // Gardez la version avec SELECT explicite et ParameterType::*
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
        $similarities = $queryBuilder
            ->select('page_id', 'similar_page_id', 'similarity_score', 'root_page_id', 'sys_language_uid')
            ->from('tx_semanticsuggestion_similarities')
            ->where(
                $queryBuilder->expr()->eq('root_page_id', $queryBuilder->createNamedParameter($parentPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($currentLanguageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->gte('similarity_score', $queryBuilder->createNamedParameter($proximityThreshold, ParameterType::STRING))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $analysisResults = []; $pageIds = [];
        foreach ($similarities as $similarity) {
            $pageId = (int)$similarity['page_id']; $similarPageId = (int)$similarity['similar_page_id'];
            $pageIds[] = $pageId; $pageIds[] = $similarPageId;
            if (!isset($analysisResults[$pageId])) {
                $analysisResults[$pageId] = ['uid' => $pageId, 'similarities' => [], 'sys_language_uid' => (int)$similarity['sys_language_uid']];
            }
            if (!in_array($similarPageId, $excludePages)) {
                $analysisResults[$pageId]['similarities'][$similarPageId] = ['score' => (float)$similarity['similarity_score'], 'relevance' => $this->determineRelevanceLevel((float)$similarity['similarity_score'])];
            }
        }
        $pageIds = array_unique($pageIds); $pageDataRecords = [];
        if (!empty($pageIds)) {
            $pageDataRecords = $this->getPageRepository()->getMenuForPages($pageIds, 'uid, title', 'sorting', 'AND hidden=0 AND deleted=0');
            foreach ($analysisResults as $pageId => &$data) { $data['title'] = ['content' => $pageDataRecords[$pageId]['title'] ?? '[Page #' . $pageId . ']']; } unset($data);
            foreach ($pageIds as $pageId) { if (!isset($analysisResults[$pageId]) && isset($pageDataRecords[$pageId])) { $analysisResults[$pageId] = ['uid' => $pageId, 'title' => ['content' => $pageDataRecords[$pageId]['title'] ?? ''], 'similarities' => [], 'sys_language_uid' => $currentLanguageUid]; } }
        }
        $statistics = $this->calculateStatisticsFromDbResults($analysisResults); // Assurez-vous que cette méthode existe
        return ['results' => $analysisResults, 'statistics' => $statistics, 'metrics' => ['totalPages' => count($pageIds)]];
    }

    protected function mergeLanguageData(array $data): array { /* ... Copiez/adaptez votre logique ... */ $firstKey = array_key_first($data); return $firstKey !== null ? $data[$firstKey] : ['results' => [], 'statistics' => [], 'metrics' => ['totalPages' => 0]]; }
    protected function calculateStatisticsFromDbResults(array $analysisResults): array { /* ... Copiez/adaptez votre logique ... */ $stats = ['topSimilarPairs' => [], 'distributionScores' => [], 'topSimilarPages' => []]; $allPairs = []; $pageSimilarityCounts = []; $scoreRanges = ['0.0-0.2'=>0, '0.2-0.4'=>0, '0.4-0.6'=>0, '0.6-0.8'=>0, '0.8-1.0'=>0]; foreach ($analysisResults as $pageId => $data) { $pageSimilarityCounts[$pageId] = 0; foreach ($data['similarities'] as $similarPageId => $similarity) { $score = $similarity['score']; $allPairs[] = ['page1'=>$pageId, 'page2'=>$similarPageId, 'score'=>$score]; $pageSimilarityCounts[$pageId]++; if($score <= 0.2) $scoreRanges['0.0-0.2']++; elseif($score <= 0.4) $scoreRanges['0.2-0.4']++; elseif($score <= 0.6) $scoreRanges['0.4-0.6']++; elseif($score <= 0.8) $scoreRanges['0.6-0.8']++; else $scoreRanges['0.8-1.0']++; } } usort($allPairs, fn($a, $b)=>$b['score']<=>$a['score']); $uniquePairs = []; $displayedPairKeys = []; foreach($allPairs as $pair){ $key = min($pair['page1'], $pair['page2']).'-'.max($pair['page1'], $pair['page2']); if(!isset($displayedPairKeys[$key])){ $uniquePairs[] = $pair; $displayedPairKeys[$key] = true; } } $stats['topSimilarPairs'] = array_slice($uniquePairs, 0, 5); $stats['distributionScores'] = $scoreRanges; arsort($pageSimilarityCounts); $stats['topSimilarPages'] = array_slice($pageSimilarityCounts, 0, 5, true); return $stats; }
    protected function getPageRepository(): PageRepository { if ($this->pageRepository === null) { $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class); } return $this->pageRepository; }
    protected function determineRelevanceLevel(float $similarityScore): string { if ($similarityScore > 0.7) return 'High'; elseif ($similarityScore > 0.4) return 'Medium'; else return 'Low'; }
    /**
     * Adds a flash message to the FlashMessageQueue.
     * Signature compatible avec TYPO3 v12 ActionController.
     *
     * @param string $messageBody The message body
     * @param string $messageTitle Optional message title
     * @param int $severity Optional severity, must correspond to ContextualFeedbackSeverity::* values (default: OK)
     * @param bool $storeInSession Optional, define if message should be stored in session (default: true)
     */
    public function addFlashMessage(
        string $messageBody,
        $messageTitle = '', // PAS de type hint string
        $severity = ContextualFeedbackSeverity::OK, // PAS de type hint int, Default OK
        $storeInSession = true // PAS de type hint bool, Default true
    ) /* PAS de : void */ {
        // Le corps de la méthode reste le même
        // Conversion sûre de $severity (qui est un int ici) en objet Enum
        $severityEnum = ContextualFeedbackSeverity::tryFrom((int)$severity) ?? ContextualFeedbackSeverity::OK;
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageBody,
            (string)$messageTitle, // Cast en string par sécurité
            $severityEnum,
            (bool)$storeInSession // Cast en bool par sécurité
        );
        $this->flashMessageService->getMessageQueueByIdentifier('core.template.flashMessages')->enqueue($flashMessage);
    }

} 