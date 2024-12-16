<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Task;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Context\Context;
use Psr\Log\LoggerInterface;

class GenerateSimilaritiesTask extends AbstractTask
{
    protected ?LoggerInterface $logger = null;
    protected ?PageAnalysisService $pageAnalysisService = null;
    protected ?ConnectionPool $connectionPool = null;
    protected ?CacheManager $cacheManager = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

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
                $languages = $site->getAllLanguages();

                foreach ($languages as $language) {
                    $this->logger->info('Processing site', [
                        'rootPageId' => $rootPageId,
                        'language' => $language->getLanguageId()
                    ]);

                    // Récupérer toutes les pages du site pour cette langue
                    $pages = $this->getAllPages($rootPageId, $language->getLanguageId());
                    
                    if (empty($pages)) {
                        $this->logger->warning('No pages found', [
                            'rootPageId' => $rootPageId,
                            'language' => $language->getLanguageId()
                        ]);
                        continue;
                    }

                    // Analyser les similarités
                    $analysisResults = $this->pageAnalysisService->analyzePages($pages, $language->getLanguageId());

                    // Sauvegarder les résultats
                    $this->saveResults($analysisResults, $rootPageId, $language->getLanguageId());
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

    protected function initializeDependencies(): void
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $this->pageAnalysisService = GeneralUtility::makeInstance(PageAnalysisService::class);
    }

    protected function getAllPages(int $rootPageId, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        
        $constraints = [
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT)),
            $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
            $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT))
        ];

        // Pour la langue par défaut
        if ($languageId === 0) {
            $constraints[] = $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($rootPageId, \PDO::PARAM_INT));
        }

        $pages = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(...$constraints)
            ->executeQuery()
            ->fetchAllAssociative();

        return $pages;
    }

    protected function saveResults(array $analysisResults, int $rootPageId, int $languageId): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
        
        // Supprimer les anciennes entrées pour ce site et cette langue
        $queryBuilder
            ->delete('tx_semanticsuggestion_similarities')
            ->where(
                $queryBuilder->expr()->eq('root_page_id', $queryBuilder->createNamedParameter($rootPageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT))
            )
            ->executeStatement();

        // Insérer les nouveaux résultats
        foreach ($analysisResults['results'] as $pageId => $pageData) {
            foreach ($pageData['similarities'] as $similarPageId => $similarity) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
                $queryBuilder
                    ->insert('tx_semanticsuggestion_similarities')
                    ->values([
                        'page_id' => $pageId,
                        'similar_page_id' => $similarPageId,
                        'similarity_score' => $similarity['score'],
                        'root_page_id' => $rootPageId,
                        'sys_language_uid' => $languageId,
                        'crdate' => time(),
                        'tstamp' => time()
                    ])
                    ->executeStatement();
            }
        }

        // Nettoyer le cache
        $this->cacheManager->getCache('semantic_suggestion')->flush();
    }
}