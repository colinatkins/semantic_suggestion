<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Task;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Psr\Log\LoggerInterface;

class GenerateSimilaritiesTask extends AbstractTask
{
    /**
     * ID de la page de départ pour l'analyse
     * @var int
     */
    public $startPageId = 1;
    
    /**
     * Liste des pages à exclure (format: "42,56,78")
     * @var string
     */
    public $excludePages = '';
    
    /**
     * Seuil minimum de similarité pour enregistrer en BDD
     * @var float
     */
    public $minimumSimilarity = 0.1; // Ajusté pour TF-IDF (scores plus bas)
    
    /**
     * Détermine si l'exclusion est récursive ou non
     * @var bool
     */
    public $recursiveExclusion = true;
    
    protected ?LoggerInterface $logger = null;
    protected ?PageAnalysisService $pageAnalysisService = null;
    protected ?ConnectionPool $connectionPool = null;
    protected ?CacheManager $cacheManager = null;
    protected ?ConfigurationManagerInterface $configurationManager = null;
    protected ?PageRepository $pageRepository = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Initialise les dépendances nécessaires
     */
    protected function initializeDependencies(): void
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $this->configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
        $this->pageAnalysisService = GeneralUtility::makeInstance(PageAnalysisService::class);
        $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
    }

    /**
     * Exécute la tâche planifiée
     */
    public function execute(): bool
    {
        try {
            $this->initializeDependencies();
            $this->logger->info('Starting similarity generation task', [
                'startPageId' => $this->startPageId,
                'minimumSimilarity' => $this->minimumSimilarity,
                'recursiveExclusion' => $this->recursiveExclusion
            ]);

            // Convertir la liste de pages exclues en tableau
            $excludePages = !empty($this->excludePages) 
                ? GeneralUtility::intExplode(',', $this->excludePages, true) 
                : [];
            
            // Récupérer toutes les pages à partir du startPageId
            $pages = $this->getPages($this->startPageId, 999, 0, $excludePages);

            if (empty($pages)) {
                $this->logger->warning('No pages found', [
                    'startPageId' => $this->startPageId
                ]);
                return true; // Retourner true car la tâche s'est exécutée correctement, même sans données
            }

            // Analyse pour chaque langue
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($this->startPageId);
            
            foreach ($site->getAllLanguages() as $language) {
                $languageId = $language->getLanguageId();
                
                $this->logger->info('Processing language', [
                    'startPageId' => $this->startPageId,
                    'language' => $languageId
                ]);

                // Analyser les similitudes
                $analysisData = $this->pageAnalysisService->analyzePages($pages, $languageId);

                // Sauvegarder les résultats
                $this->saveResults($analysisData, $this->startPageId, $languageId, $this->minimumSimilarity);
            }

            $this->logger->info('Similarity generation task completed successfully');
            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error during similarity generation task', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Récupère récursivement les pages à partir de l'ID parent
     */
    protected function getPages(int $parentPageId, int $depth, int $languageId, array $excludePages = []): array
    {
        $allPages = [];
        
        try {
            // Récupérer les pages directement sous le parent
            $pages = $this->pageRepository->getMenu(
                $parentPageId,
                '*',
                'sorting',
                'AND hidden=0 AND deleted=0',
                false,
                false, // disableGroupAccessCheck doit être un booléen
                $languageId
            );
            
            foreach ($pages as $page) {
                // Vérifier si la page est exclue
                $isExcluded = in_array($page['uid'], $excludePages);
                
                if ($isExcluded) {
                    // Si l'exclusion n'est pas récursive, on continue quand même 
                    // pour analyser les sous-pages SANS les exclure
                    if (!$this->recursiveExclusion && $depth > 1) {
                        // IMPORTANT : On ne passe pas $excludePages pour les sous-pages
                        // car on veut seulement exclure la page courante, pas ses enfants
                        $subPages = $this->getPages($page['uid'], $depth - 1, $languageId, []);
                        $allPages = array_merge($allPages, $subPages);
                    }
                    // Dans tous les cas, on ignore la page courante
                    continue;
                }
                
                // Ajouter la page si elle n'est pas exclue
                $allPages[$page['uid']] = $page;
                $allPages[$page['uid']]['sys_language_uid'] = $languageId;
                
                // Descendre récursivement si la profondeur le permet
                if ($depth > 1) {
                    // Pour les pages non-exclues, on continue avec la liste complète des exclusions
                    $subPages = $this->getPages($page['uid'], $depth - 1, $languageId, $excludePages);
                    $allPages = array_merge($allPages, $subPages);
                }
            }
            
            return $allPages;
            
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving pages', [
                'parentId' => $parentPageId,
                'languageId' => $languageId,
                'exception' => $e->getMessage()
            ]);
            return $allPages;
        }
    }


    /**
     * Sauvegarde les résultats d'analyse en base de données
     */
    protected function saveResults(array $analysisData, int $rootPageId, int $languageId, float $proximityThreshold): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_semanticsuggestion_similarities');
        
        try {
            // Commencer une transaction
            $connection->beginTransaction();
            
            // Supprimer les anciennes entrées pour ce site et cette langue
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
            
            // Version compatible TYPO3 v12 et v13
            $queryBuilder
                ->delete('tx_semanticsuggestion_similarities')
                ->where(
                    $queryBuilder->expr()->eq('root_page_id', $queryBuilder->createNamedParameter($rootPageId)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId))
                )
                ->executeStatement();
            
            // Préparer les insertions en lots
            $bulkInserts = [];
            $now = time();
            
            $this->logger->info('Using threshold for filtering', ['threshold' => $proximityThreshold]);
            
        foreach ($analysisData['results'] as $pageId => $pageData) {
            // Vérifier que 'similarities' existe et est un tableau
            if (!isset($pageData['similarities']) || !is_array($pageData['similarities'])) {
                $this->logger->warning('No similarities found for page', ['pageId' => $pageId]);
                continue;
            }
            
            foreach ($pageData['similarities'] as $similarPageId => $similarity) {
                    // Ne stocker que les similarités au-dessus du seuil
                    if ($similarity['score'] >= $proximityThreshold) {
                        $bulkInserts[] = [
                            'page_id' => $pageId,
                            'similar_page_id' => $similarPageId,
                            'similarity_score' => $similarity['score'],
                            'root_page_id' => $rootPageId, // Utiliser l'ID fourni
                            'sys_language_uid' => $languageId,
                            'crdate' => $now,
                            'tstamp' => $now
                        ];
                    }
                    
                    // Insérer par lots pour optimiser les performances
                    if (count($bulkInserts) >= 100) {
                        $this->bulkInsert($bulkInserts);
                        $bulkInserts = [];
                    }
                }
            }
            
            // Insérer les enregistrements restants
            if (!empty($bulkInserts)) {
                $this->bulkInsert($bulkInserts);
            }
            
            // Valider la transaction
            $connection->commit();
            
            $this->logger->info('Similarities saved to database', [
                'rootPageId' => $rootPageId,
                'languageId' => $languageId,
                'similaritiesCount' => count($bulkInserts)
            ]);
            
            // Vider le cache pour ce site
            $this->cacheManager->getCache('semantic_suggestion')->flushByTag('site_' . $rootPageId);
            
        } catch (\Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            
            $this->logger->error('Failed to save similarities', [
                'rootPageId' => $rootPageId,
                'languageId' => $languageId,
                'exception' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Effectue une insertion en lot dans la table des similarités
     */
    protected function bulkInsert(array $records): void
    {
        if (empty($records)) {
            return;
        }
        
        $connection = $this->connectionPool->getConnectionForTable('tx_semanticsuggestion_similarities');
        
        // Utiliser bulkInsert pour une meilleure performance
        $connection->bulkInsert(
            'tx_semanticsuggestion_similarities',
            $records,
            ['page_id', 'similar_page_id', 'similarity_score', 'root_page_id', 'sys_language_uid', 'crdate', 'tstamp']
        );
    }

    /**
     * Retourne des informations supplémentaires à afficher dans la liste des tâches du scheduler
     */
    public function getAdditionalInformation(): string
    {
        $info = [];
        
        // Ajouter l'ID de la page de départ
        $info[] = '📄 Page de départ: ' . $this->startPageId;
        
        // Ajouter les pages exclues si définies
        if (!empty($this->excludePages)) {
            $excludeList = GeneralUtility::trimExplode(',', $this->excludePages, true);
            $info[] = '🚫 Pages exclues: ' . implode(', ', $excludeList) . ' (' . count($excludeList) . ')';
        } else {
            $info[] = '🚫 Pages exclues: aucune';
        }
        
        // Ajouter l'exclusion récursive
        $recursiveIcon = $this->recursiveExclusion ? '🔄' : '📄';
        $recursiveText = $this->recursiveExclusion ? 'Récursive' : 'Page seule';
        $info[] = $recursiveIcon . ' Exclusion: ' . $recursiveText;
        
        // Ajouter le seuil de similarité
        $info[] = '📊 Seuil minimum: ' . number_format($this->minimumSimilarity, 2);
        
        return implode(' | ', $info);
    }
}