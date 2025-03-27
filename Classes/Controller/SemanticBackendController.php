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
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;


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
    protected ?ConnectionPool $connectionPool = null;

    // --- Constructeur pour DI (v13) ---
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        PageAnalysisService $pageAnalysisService,
        LogManager $logManager,
        LanguageService $languageService,
        FlashMessageService $flashMessageService,
        PageRepository $pageRepository,
        ConnectionPool $connectionPool = null  // Optionnel au cas où il n'est pas défini dans Services.php
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->pageAnalysisService = $pageAnalysisService;
        $this->logger = $logManager->getLogger(__CLASS__);
        $this->languageService = $languageService;
        $this->flashMessageService = $flashMessageService;
        $this->pageRepository = $pageRepository;
        $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
    }

    
        /**
         * @param PageRepository $pageRepository
         */
        public function injectPageRepository(PageRepository $pageRepository): void
        {
            $this->pageRepository = $pageRepository;
        }


    // --- Action Index (Logique métier principale) ---
// --- Action Index (Logique métier principale) ---
public function indexAction(int $rootPageId = null): ResponseInterface
{
    // Log facultatif au début
    if ($this->logger instanceof LoggerInterface &&
        ($this->pageAnalysisService->getSettings()['debugMode'] ?? false)) {
        $this->logger->debug('Début de indexAction', ['rootPageId' => $rootPageId]);
    }

    // Créer le ModuleTemplate
    $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

    try {
        // Récupérer la configuration TypoScript
        $fullTypoScript = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        $extensionConfig = $fullTypoScript['plugin.']['tx_semanticsuggestion_suggestions.']['settings.'] ?? [];
        $this->pageAnalysisService->setSettings($extensionConfig); // Mettre à jour les settings du service

        // Si aucun rootPageId n'est fourni, tenter de le déterminer
        if ($rootPageId === null) {
            $defaultRootPageId = (int)($extensionConfig['parentPageId'] ?? 0);

            // Si pas dans TS, chercher le premier root_page_id distinct dans la DB
            if ($defaultRootPageId === 0) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
                $rootPageIds = $queryBuilder
                    ->select('root_page_id')
                    ->from('tx_semanticsuggestion_similarities')
                    ->groupBy('root_page_id')
                    ->executeQuery()
                    ->fetchFirstColumn();

                if (!empty($rootPageIds)) {
                    $defaultRootPageId = (int)$rootPageIds[0];
                }
            }
            $rootPageId = $defaultRootPageId;
        }

        // Vérifier si un rootPageId valide a été trouvé ou fourni
        if ($rootPageId <= 0) {
            $this->addFlashMessage(
                'Aucune analyse de similarité trouvée ou configurée. Veuillez configurer l\'extension et exécuter la tâche scheduler correspondante.',
                'Aucune donnée disponible',
                ContextualFeedbackSeverity::INFO // Utiliser l'Enum ici
            );
             // ATTENTION: ModuleTemplate n'a pas de setContent. Retourner directement si erreur.
            // Il faudrait idéalement assigner une variable d'erreur à la vue.
            // Pour l'instant, on peut juste rendre sans données principales.
            $moduleTemplate->assign('errorMessage', 'Aucune analyse de similarité trouvée ou configurée.');
            return $moduleTemplate->renderResponse(); // Retourner tôt
        }

        // Récupérer les analyses disponibles pour le sélecteur
        $queryBuilderAnalyses = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');

        $availableAnalyses = $queryBuilderAnalyses
            ->select('root_page_id')
            ->addSelectLiteral('COUNT(DISTINCT page_id) as page_count')
            ->addSelectLiteral('COUNT(*) as pair_count')
            ->from('tx_semanticsuggestion_similarities')
            ->groupBy('root_page_id')
            ->executeQuery()
            ->fetchAllAssociative();

        // Enrichir avec le titre de la page racine
        foreach ($availableAnalyses as &$analysis) {
            $pageRecord = $this->pageRepository->getPage((int)$analysis['root_page_id']);
            $analysis['title'] = $pageRecord['title'] ?? 'ID: ' . $analysis['root_page_id'];
        }
        unset($analysis); // Important après une boucle foreach avec référence

        // Récupérer les données pour l'analyse sélectionnée depuis la DB
        // Note: getAnalysisFromDatabase utilise getAnalysisFromDatabaseDetailed
        // qui calcule déjà les 'statistics'.
        $analysisData = $this->getAnalysisFromDatabase($rootPageId);

        // Assigner toutes les données nécessaires à la vue Fluid
        $moduleTemplate->assignMultiple([
            'currentRootPageId' => $rootPageId,
            'availableAnalyses' => $availableAnalyses,
            'parentPageId' => $rootPageId, // ou $extensionConfig['parentPageId'] selon la logique voulue
            'proximityThreshold' => (float)($extensionConfig['proximityThreshold'] ?? 0.5),
            'maxSuggestions' => (int)($extensionConfig['maxSuggestions'] ?? 5),
            'excludePages' => $extensionConfig['excludePages'] ?? '',
            'analysisResults' => $analysisData['results'] ?? [],
            'statistics' => $analysisData['statistics'] ?? [], // <-- Utilise les statistiques pré-calculées
            'showStatistics' => true, // Ou basé sur $extensionConfig['showStatistics']
            'showPerformanceMetrics' => true, // etc.
            'showLanguageStatistics' => true,
            'showTopSimilarPairs' => true,
            'showDistributionScores' => true,
            'showTopSimilarPages' => true,
            // Ajouter d'autres variables si nécessaire (ex: performanceMetrics, languageStatistics calculés ici)
        ]);

    } catch (\Exception $e) {
        // Gérer les erreurs potentielles lors de la récupération/traitement des données
        $this->logger->error('Error in indexAction', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        $this->addFlashMessage(
            'Une erreur est survenue lors du traitement des données: ' . $e->getMessage(),
            'Erreur',
            ContextualFeedbackSeverity::ERROR // Utiliser l'Enum ici
        );
         // Assigner un message d'erreur à la vue en cas d'exception
         $moduleTemplate->assign('errorMessage', 'Une erreur est survenue lors du traitement des données: ' . $e->getMessage());
    }

    // --- SUPPRIMER LE BLOC SUIVANT ---
    /*
    try {
        $content = $this->view->render(); // Inutile de rendre manuellement ici
        $moduleTemplate->setContent($content); // Erreur: Méthode inexistante
    } catch (\Exception $e) {
        $this->logger->error('Error rendering view', ['exception' => $e->getMessage()]);
        $this->addFlashMessage(
            'Une erreur est survenue lors du rendu de la vue: ' . $e->getMessage(),
            'Erreur de rendu',
            ContextualFeedbackSeverity::ERROR
        );
         // Si le rendu échoue dans renderResponse, TYPO3 le gère souvent.
         // On peut assigner un message d'erreur si besoin.
         // $moduleTemplate->assign('renderError', 'Erreur de rendu: ' . $e->getMessage());
         // $moduleTemplate->setContent('Une erreur est survenue lors du rendu de la vue.'); // Inexistant
    }
    */
    // --- FIN DE LA SUPPRESSION ---

    // Rendre la réponse complète (layout + contenu de la vue Fluid)
    // Si une exception a eu lieu avant, $errorMessage sera affiché par la vue.
    return $moduleTemplate->renderResponse('SemanticBackend/Index');
}


    private function getAnalysisFromDatabase(int $rootPageId): array
        {
            // Valeurs par défaut
            $proximityThreshold = 0.5;
            $excludePages = [];
            $currentLanguageUid = 0; // Utilisez une valeur par défaut ou un service pour obtenir la langue courante
            
            return $this->getAnalysisFromDatabaseDetailed($rootPageId, 1, $proximityThreshold, $excludePages, $currentLanguageUid);
        }


    // --- Méthodes Utilitaires (Identiques à Legacy) ---

    protected function getAnalysisFromDatabaseDetailed(int $parentPageId, int $depth, float $proximityThreshold, array $excludePages, int $currentLanguageUid): array
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