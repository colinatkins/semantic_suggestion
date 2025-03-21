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
    protected ?LoggerInterface $logger = null;
    protected ?PageAnalysisService $pageAnalysisService = null;
    protected ?ConnectionPool $connectionPool = null;
    protected ?CacheManager $cacheManager = null;
    protected ?ConfigurationManagerInterface $configurationManager = null;
    protected ?PageRepository $pageRepository = null;
    
    /**
     * Configuration par défaut si non spécifiée dans TypoScript
     */
    protected array $defaultConfig = [
        'parentPageId' => 1,
        'proximityThreshold' => 0.5,
        'maxSuggestions' => 5,
        'excerptLength' => 150,
        'recursive' => 1,
        'excludePages' => '',
        'recencyWeight' => 0.2
    ];
    
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
            $this->logger->info('Starting similarity generation task');

            // Récupérer tous les sites
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $sites = $siteFinder->getAllSites();

            foreach ($sites as $site) {
                $rootPageId = $site->getRootPageId();
                $siteConfig = $this->getSiteConfiguration($rootPageId);
                
                $this->logger->info('Processing site', [
                    'rootPageId' => $rootPageId,
                    'config' => $siteConfig
                ]);
                
                $parentPageId = (int)($siteConfig['parentPageId'] ?? $rootPageId);
                $depth = (int)($siteConfig['recursive'] ?? 1);
                $excludePages = GeneralUtility::intExplode(',', $siteConfig['excludePages'] ?? '', true);
                
                // Appliquer les paramètres à PageAnalysisService
                $this->pageAnalysisService->setSettings($siteConfig);

                $languages = $site->getAllLanguages();

                foreach ($languages as $language) {
                    $languageId = $language->getLanguageId();
                    
                    $this->logger->info('Processing language', [
                        'rootPageId' => $rootPageId,
                        'parentPageId' => $parentPageId,
                        'language' => $languageId
                    ]);

                    // Récupérer toutes les pages du site pour cette langue avec la profondeur configurée
                    $pages = $this->getPages($parentPageId, $depth, $languageId, $excludePages);

                    if (empty($pages)) {
                        $this->logger->warning('No pages found', [
                            'parentPageId' => $parentPageId,
                            'language' => $languageId,
                            'depth' => $depth
                        ]);
                        continue;
                    }

                    // Analyser les similarités
                    $analysisData = $this->pageAnalysisService->analyzePages($pages, $languageId);

                    // Sauvegarder les résultats
                    $this->saveResults($analysisData, $rootPageId, $languageId, (float)($siteConfig['proximityThreshold'] ?? 0.5));
                }
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
     * Récupère la configuration TypoScript pour le site
     */
    protected function getSiteConfiguration(int $rootPageId): array
    {
        try {
            // Récupérer la configuration du site à partir de TypoScript
            $fullTypoScript = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            
            $siteConfig = $fullTypoScript['plugin.']['tx_semanticsuggestion_suggestions.']['settings.'] ?? [];
            
            // Fusionner avec les valeurs par défaut pour s'assurer que tous les paramètres nécessaires sont présents
            return array_merge($this->defaultConfig, $siteConfig);
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not retrieve site configuration, using defaults', [
                'rootPageId' => $rootPageId,
                'exception' => $e->getMessage()
            ]);
            return $this->defaultConfig;
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
                // Ignorer les pages exclues
                if (in_array($page['uid'], $excludePages)) {
                    continue;
                }
                
                $allPages[$page['uid']] = $page;
                $allPages[$page['uid']]['sys_language_uid'] = $languageId;
                
                // Descendre récursivement si la profondeur le permet
                if ($depth > 1) {
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
            // Commencer une transaction pour optimiser les performances
            $connection->beginTransaction();
            
            // Supprimer les anciennes entrées pour ce site et cette langue
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
            $queryBuilder
                ->delete('tx_semanticsuggestion_similarities')
                ->where(
                    $queryBuilder->expr()->eq('root_page_id', $queryBuilder->createNamedParameter($rootPageId, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT))
                )
                ->executeStatement();
            
            // Préparer les insertions en lots
            $bulkInserts = [];
            $now = time();
            
            foreach ($analysisData['results'] as $pageId => $pageData) {
                foreach ($pageData['similarities'] as $similarPageId => $similarity) {
                    // Ne stocker que les similarités au-dessus du seuil
                    if ($similarity['score'] >= $proximityThreshold) {
                        $bulkInserts[] = [
                            'page_id' => $pageId,
                            'similar_page_id' => $similarPageId,
                            'similarity_score' => $similarity['score'],
                            'root_page_id' => $rootPageId,
                            'sys_language_uid' => $languageId,
                            'crdate' => $now,
                            'tstamp' => $now
                        ];
                    }
                    
                    // Insérer par lots de 100 pour optimiser les performances
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
}