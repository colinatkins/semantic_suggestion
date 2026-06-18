<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Service;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Cywolf\NlpTools\Service\LanguageDetectionService;
use Cywolf\NlpTools\Service\TextAnalysisService;
use Cywolf\NlpTools\Service\TextVectorizerService;
use TalanHdf\SemanticSuggestion\Service\SiteLanguageService;

class PageAnalysisService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected Context $context;
    protected ConfigurationManagerInterface $configurationManager;
    protected array $settings;
    protected ?CacheManager $cacheManager;
    protected ConnectionPool $connectionPool;
    protected ?QueryBuilder $queryBuilder = null;
    protected SiteFinder $siteFinder;
    protected FrontendInterface $cache;
    protected ?LanguageDetectionService $languageDetector = null;
    protected ?TextAnalysisService $textAnalyzer = null;
    protected ?TextVectorizerService $textVectorizer = null;
    protected ?SiteLanguageService $siteLanguageService;

    public function __construct(
        Context $context,
        ConfigurationManagerInterface $configurationManager,
        SiteFinder $siteFinder,
        ?CacheManager $cacheManager = null,
        ?ConnectionPool $connectionPool = null,
        ?LoggerInterface $logger = null,
        ?SiteLanguageService $siteLanguageService = null,
        ?LanguageDetectionService $languageDetector = null,
        ?TextAnalysisService $textAnalyzer = null,
        ?TextVectorizerService $textVectorizer = null
    ) {
        $this->context = $context;
        $this->configurationManager = $configurationManager;
        $this->siteFinder = $siteFinder;
        $this->siteLanguageService = $siteLanguageService ?? GeneralUtility::makeInstance(SiteLanguageService::class);
        $this->cacheManager = $cacheManager;
        $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
        $this->logger = $logger ?? new NullLogger();

        if ($logger !== null) {
            $this->setLogger($logger);
        }

        $this->settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'semanticsuggestion_suggestions'
        );

        $this->initializeSettings();
        $this->initializeCache();
        $this->initializeNlpServices($languageDetector, $textAnalyzer, $textVectorizer);
    }

    protected function initializeNlpServices(
        ?LanguageDetectionService $languageDetector = null,
        ?TextAnalysisService $textAnalyzer = null,
        ?TextVectorizerService $textVectorizer = null
    ): void {
        $this->languageDetector = $languageDetector ?? GeneralUtility::makeInstance(LanguageDetectionService::class);
        $this->textAnalyzer = $textAnalyzer ?? GeneralUtility::makeInstance(TextAnalysisService::class);
        $this->textVectorizer = $textVectorizer ?? GeneralUtility::makeInstance(TextVectorizerService::class);

        if ($this->cache) {
            if (method_exists($this->textAnalyzer, 'setCache')) {
                $this->textAnalyzer->setCache($this->cache);
            }
            if (method_exists($this->textVectorizer, 'setCache')) {
                $this->textVectorizer->setCache($this->cache);
            }
        }
    }

    private function logDebug(string $message, array $context = []): void
    {
        if (isset($this->settings['debugMode']) && $this->settings['debugMode']) {
            $this->logger->debug($message, $context);
        }
    }

    protected function initializeSettings(): void
    {
        $this->settings['debugMode'] = (bool)($this->settings['debugMode'] ?? false);

        $this->settings['recencyWeight'] = max(0, min(1, (float)($this->settings['recencyWeight'] ?? 0.2)));

        $this->settings['analyzedFields'] = $this->settings['analyzedFields'] ?? [
            'title' => 1.5,
            'description' => 1.0,
            'keywords' => 2.0,
            'abstract' => 1.2,
            'content' => 1.0
        ];
    }

    protected function initializeCache(): void
    {
        if ($this->cacheManager !== null) {
            try {
                $this->cache = $this->cacheManager->getCache('semantic_suggestion');
            } catch (\TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException $e) {
                $this->cache = $this->cacheManager->getCache('null');
            }
        } else {
            $this->cache = new class implements FrontendInterface {
                private array $data = [];
                public function set($entryIdentifier, $data, array $tags = [], $lifetime = null): void {
                    $this->data[$entryIdentifier] = $data;
                }
                public function get($entryIdentifier) {
                    return $this->data[$entryIdentifier] ?? false;
                }
                public function has($entryIdentifier): bool {
                    return isset($this->data[$entryIdentifier]);
                }
                public function remove($entryIdentifier): void {
                    unset($this->data[$entryIdentifier]);
                }
                public function flush(): void {
                    $this->data = [];
                }
                public function flushByTag($tag): void {}
                public function flushByTags(array $tags): void {}
                public function collectGarbage(): void {}
                public function isValidEntryIdentifier($identifier): bool {
                    return is_string($identifier);
                }
                public function isValidTag($tag): bool {
                    return is_string($tag);
                }
                public function getIdentifier(): string {
                    return 'fallback_cache';
                }
                public function getBackend() {
                    return null;
                }
            };
        }
    }

    public function setSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
        $this->initializeSettings();
    }

    protected function getQueryBuilder(string $table = 'pages'): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable($table);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCacheManager(): ?CacheManager
    {
        return $this->cacheManager;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getConnectionPool(): ?ConnectionPool
    {
        return $this->connectionPool;
    }

    protected function getCurrentLanguage(): string
    {
        if ($this->siteLanguageService !== null) {
            $languageAspect = $this->context->getAspect('language');
            $languageId = $languageAspect->getId();
            $currentPageId = $this->getCurrentPageId();

            $siteLanguageCode = $this->siteLanguageService->getLanguageCodeByUid($languageId, $currentPageId);
            if ($siteLanguageCode !== null) {
                $this->logDebug('Language detected from TYPO3 site configuration', [
                    'languageId' => $languageId,
                    'pageId' => $currentPageId,
                    'detectedCode' => $siteLanguageCode
                ]);
                return $siteLanguageCode;
            }
        }

        $typoscriptLanguage = $this->getLanguageFromTypoScript(
            $this->context->getAspect('language')->getId()
        );
        if ($typoscriptLanguage !== null) {
            return $typoscriptLanguage;
        }

        return $this->settings['defaultLanguage'] ?? 'en';
    }

    protected function getCurrentPageId(): ?int
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $pageArguments = $request->getAttribute('routing');
            if ($pageArguments instanceof PageArguments) {
                return $pageArguments->getPageId();
            }

            $pageId = $request->getQueryParams()['id'] ?? null;
            if ($pageId !== null) {
                return (int)$pageId;
            }
        }

        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->id) {
            return (int)$GLOBALS['TSFE']->id;
        }

        if ($this->context->hasAspect('page')) {
            $pageId = $this->context->getPropertyFromAspect('page', 'id', 0);
            if ($pageId > 0) {
                return $pageId;
            }
        }

        $pageId = (int)($_GET['id'] ?? 0);
        if ($pageId > 0) {
            return $pageId;
        }

        return null;
    }

    protected function isBackendContext(): bool
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }

        if (isset($_SERVER['REQUEST_URI']) &&
            strpos($_SERVER['REQUEST_URI'], '/typo3/module/') !== false) {
            return true;
        }

        if (isset($GLOBALS['TYPO3_REQUEST']) &&
            $GLOBALS['TYPO3_REQUEST']->getAttribute('applicationType') === 'BE') {
            return true;
        }

        return false;
    }

    protected function getLanguageFromTypoScript(int $languageId): ?string
    {
        $typoscriptMapping = $this->settings['languageMapping'] ?? [];
        return $typoscriptMapping[$languageId] ?? null;
    }

    protected function getCurrentLanguageUid(): int
    {
        return GeneralUtility::makeInstance(Context::class)->getAspect('language')->getId();
    }

    public function analyzePages(array $pages, int $currentLanguageUid): array
    {
        $startTime = microtime(true);

        if (empty($pages)) {
            $this->logger?->warning('No pages provided for analysis');
            return [
                'results' => [],
                'metrics' => [
                    'executionTime' => microtime(true) - $startTime,
                    'totalPages' => 0,
                    'similarityCalculations' => 0,
                    'fromCache' => false,
                ],
            ];
        }

        $language = $this->getCurrentLanguage();

        $this->logDebug('Starting page analysis', [
            'pageCount' => count($pages),
            'languageUid' => $currentLanguageUid,
            'language' => $language
        ]);

        $firstPage = null;
        foreach ($pages as $page) {
            if ($page !== null) {
                $firstPage = $page;
                break;
            }
        }

        if ($firstPage === null) {
            $this->logger?->warning('No valid pages found in the provided array');
            return [
                'results' => [],
                'metrics' => [
                    'executionTime' => microtime(true) - $startTime,
                    'totalPages' => 0,
                    'similarityCalculations' => 0,
                    'fromCache' => false,
                ],
            ];
        }

        $parentPageId = $firstPage['pid'] ?? 0;
        $depth = $this->calculateDepth($pages);
        $cacheIdentifier = "semantic_analysis_{$parentPageId}_{$depth}_{$language}";

        if ($this->cache->has($cacheIdentifier)) {
            $cachedResult = $this->cache->get($cacheIdentifier);
            $cachedResult['metrics']['fromCache'] = true;
            $cachedResult['metrics']['executionTime'] = microtime(true) - $startTime;
            return $cachedResult;
        }

        $totalPages = count($pages);
        $analysisResults = [];

        // Batch-fetch all tt_content for every page in a single query
        $allPageUids = array_values(array_filter(array_column(array_values($pages), 'uid')));
        $batchedContent = $this->batchGetPageContent($allPageUids, $currentLanguageUid);

        foreach ($pages as $page) {
            if (isset($page['uid'])) {
                $analysisResults[$page['uid']] = $this->preparePageData($page, $currentLanguageUid, $batchedContent);
            } else {
                $this->logger?->warning('Page without UID encountered', ['page' => $page]);
            }
        }

        // Build TF-IDF vectors once for the entire corpus
        $corpusTexts = [];
        $corpusPageIds = [];
        foreach ($analysisResults as $pageId => $pageData) {
            $text = $this->prepareTextForAnalysis($pageData);
            if ($text !== '') {
                $corpusTexts[] = $text;
                $corpusPageIds[] = $pageId;
            }
        }

        $prebuiltVectors = [];
        if (!empty($corpusTexts)) {
            $sparseVectors = $this->buildSparseTfIdfVectors($corpusTexts, $language);
            foreach ($corpusPageIds as $idx => $pageId) {
                if (isset($sparseVectors[$idx])) {
                    $prebuiltVectors[$pageId] = $sparseVectors[$idx];
                }
            }
            $this->logDebug('Corpus sparse TF-IDF vectors built', [
                'pageCount' => count($corpusPageIds),
            ]);
        }

        // Pre-compute language per page once — avoids O(n²) detectLanguageForPage calls
        $pageLanguages = [];
        foreach ($analysisResults as $pageId => $pageData) {
            if ($this->siteLanguageService !== null) {
                $pageLanguages[$pageId] = $this->siteLanguageService->detectLanguageForPage($pageData, $this->languageDetector);
            } else {
                $pageLanguages[$pageId] = $this->languageDetector->detectLanguage($this->prepareTextForAnalysis($pageData));
            }
        }

        // Triangular loop: compute each pair once and store symmetrically
        $similarityCalculations = 0;
        $pageIdList = array_keys($analysisResults);
        $pageCount = count($pageIdList);

        for ($i = 0; $i < $pageCount; $i++) {
            $pageId = $pageIdList[$i];
            $lang1 = $pageLanguages[$pageId] ?? 'en';

            for ($j = $i + 1; $j < $pageCount; $j++) {
                $comparisonPageId = $pageIdList[$j];
                $lang2 = $pageLanguages[$comparisonPageId] ?? 'en';
                $similarityCalculations++;

                if ($this->siteLanguageService !== null
                    && !$this->siteLanguageService->areLanguagesCompatible($lang1, $lang2)
                ) {
                    continue;
                }

                if (isset($prebuiltVectors[$pageId], $prebuiltVectors[$comparisonPageId])) {
                    $semanticSimilarity = $this->normalizedDotProduct(
                        $prebuiltVectors[$pageId],
                        $prebuiltVectors[$comparisonPageId]
                    );
                } else {
                    $text1 = $this->prepareTextForAnalysis($analysisResults[$pageId]);
                    $text2 = $this->prepareTextForAnalysis($analysisResults[$comparisonPageId]);
                    if (empty($text1) || empty($text2)) {
                        continue;
                    }
                    $pairVectors = $this->buildSparseTfIdfVectors([$text1, $text2], $lang1);
                    if (count($pairVectors) < 2) {
                        continue;
                    }
                    $semanticSimilarity = $this->normalizedDotProduct(
                        $pairVectors[0],
                        $pairVectors[1]
                    );
                }

                $recencyWeight = $this->settings['recencyWeight'] ?? 0.2;
                $recencyBoost = $this->calculateRecencyBoost($analysisResults[$pageId], $analysisResults[$comparisonPageId]);
                $finalSimilarity = min(
                    ($semanticSimilarity * (1 - $recencyWeight)) + ($recencyBoost * $recencyWeight),
                    1.0
                );

                if ($finalSimilarity > 0) {
                    $commonKeywords = $this->findCommonKeywords($analysisResults[$pageId], $analysisResults[$comparisonPageId]);
                    $relevance = $this->determineRelevance($finalSimilarity);

                    $analysisResults[$pageId]['similarities'][$comparisonPageId] = [
                        'score' => $finalSimilarity,
                        'semanticSimilarity' => $semanticSimilarity,
                        'recencyBoost' => $recencyBoost,
                        'commonKeywords' => $commonKeywords,
                        'relevance' => $relevance,
                        'ageInDays' => round((time() - ($analysisResults[$comparisonPageId]['content_modified_at'] ?? time())) / (24 * 3600), 1),
                    ];
                    $analysisResults[$comparisonPageId]['similarities'][$pageId] = [
                        'score' => $finalSimilarity,
                        'semanticSimilarity' => $semanticSimilarity,
                        'recencyBoost' => $recencyBoost,
                        'commonKeywords' => $commonKeywords,
                        'relevance' => $relevance,
                        'ageInDays' => round((time() - ($analysisResults[$pageId]['content_modified_at'] ?? time())) / (24 * 3600), 1),
                    ];
                }
            }
        }

        $result = [
            'results' => $analysisResults,
            'metrics' => [
                'executionTime' => microtime(true) - $startTime,
                'totalPages' => $totalPages,
                'similarityCalculations' => $similarityCalculations,
                'fromCache' => false,
            ],
        ];

        $this->cache->set(
            $cacheIdentifier,
            $result,
            ['tx_semanticsuggestion', "pages_{$parentPageId}"],
            86400
        );

        $this->logDebug('Analysis complete', [
            'executionTime' => microtime(true) - $startTime,
            'totalPages' => $totalPages,
            'similarityCalculations' => $similarityCalculations
        ]);

        return $result;
    }

    /**
     * Sparse TF-IDF vectorizer.
     *
     * The vendor's createTfIdfVectors() builds DENSE vectors: every document gets
     * an entry for every vocabulary term, including zero-valued ones. With 800 pages
     * the vocabulary reaches ~50 k unique stems; 800 × 50 k × ~80 B PHP array
     * overhead = several GB → OOM.
     *
     * This implementation stores only non-zero entries per document. Memory scales
     * with actual term usage, not vocabulary size. The result is L2-normalized so
     * normalizedDotProduct() gives exact cosine similarity.
     *
     * @param  string[] $texts     One entry per document (order preserved in returned indices)
     * @param  string   $language  ISO 639-1 code for stop-word removal and stemming
     * @return array<int, array<string, float>>  Sparse, L2-normalized TF-IDF vectors
     */
    private function buildSparseTfIdfVectors(array $texts, string $language): array
    {
        if (empty($texts) || $this->textAnalyzer === null) {
            return [];
        }

        // 1. Tokenize every document (stop-word removal + stemming)
        $tokenizedDocs = [];
        foreach ($texts as $idx => $text) {
            $clean  = $this->textAnalyzer->removeStopWords(mb_substr($text, 0, 5000), $language);
            $tokens = $this->textAnalyzer->stem($clean, $language);
            $tokenizedDocs[$idx] = $tokens;
        }

        // 2. Document frequencies (how many documents contain each term)
        $docFreq = [];
        foreach ($tokenizedDocs as $tokens) {
            foreach (array_unique($tokens) as $term) {
                $docFreq[$term] = ($docFreq[$term] ?? 0) + 1;
            }
        }

        // 3. Smoothed IDF: log((N+1)/(df+1)) + 1
        $numDocs = count($texts);
        $idf = [];
        foreach ($docFreq as $term => $freq) {
            $idf[$term] = log(($numDocs + 1) / ($freq + 1)) + 1.0;
        }

        // 4. Sparse TF-IDF + L2 normalization (only non-zero entries stored)
        $vectors = [];
        foreach ($tokenizedDocs as $idx => $tokens) {
            if (empty($tokens)) {
                $vectors[$idx] = [];
                continue;
            }

            $termFreq = array_count_values($tokens);
            $sparse   = [];
            $mag2     = 0.0;

            foreach ($termFreq as $term => $freq) {
                if (!isset($idf[$term])) {
                    continue;
                }
                $val = (float)$freq * $idf[$term];
                if ($val !== 0.0) {
                    $sparse[$term] = $val;
                    $mag2 += $val * $val;
                }
            }

            if ($mag2 > 0.0) {
                $mag = sqrt($mag2);
                foreach ($sparse as $term => $val) {
                    $sparse[$term] = $val / $mag;
                }
            }

            $vectors[$idx] = $sparse;
        }

        return $vectors;
    }

    /**
     * Dot product of two pre-normalized (L2) vectors sharing the same vocabulary.
     * Equivalent to cosine similarity when both vectors have magnitude 1.
     * Iterates only over non-zero terms in v1 (sparse), avoiding full vocabulary scan.
     */
    private function normalizedDotProduct(array $v1, array $v2): float
    {
        $dot = 0.0;
        foreach ($v1 as $term => $value) {
            if ($value !== 0.0 && isset($v2[$term])) {
                $dot += $value * $v2[$term];
            }
        }
        return min($dot, 1.0);
    }

    private function calculateDepth(array $pages): int
    {
        $maxDepth = 0;
        $pagesByUid = [];

        foreach ($pages as $page) {
            if (isset($page['uid'])) {
                $pagesByUid[$page['uid']] = $page;
            }
        }

        foreach ($pages as $page) {
            $depth = 1;
            $currentPage = $page;
            while (isset($currentPage['pid']) && $currentPage['pid'] !== 0 && isset($pagesByUid[$currentPage['pid']])) {
                $depth++;
                $currentPage = $pagesByUid[$currentPage['pid']];
            }
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }

    protected function preparePageData(array $page, int $currentLanguageUid, array $batchedContent = []): array
    {
        $preparedData = [
            'uid' => $page['uid'],
            'sys_language_uid' => $page['sys_language_uid'] ?? 0,
            'isTranslation' => isset($page['_PAGES_OVERLAY']),
        ];

        $language = $this->getCurrentLanguage();

        foreach ($this->settings['analyzedFields'] as $field => $weight) {
            $originalContent = $page[$field] ?? '';

            if ($field === 'content' && empty($originalContent)) {
                $originalContent = $batchedContent[$page['uid']]
                    ?? $this->getPageContent($page['uid'], $currentLanguageUid);
            }

            if (!empty($originalContent) && is_string($originalContent)) {
                $processedContent = $this->textAnalyzer->removeStopWords($originalContent, $language);

                if ($this->settings['enableStemming'] ?? true) {
                    $stemmedWords = $this->textAnalyzer->stem($processedContent, $language);
                    $processedContent = implode(' ', $stemmedWords);
                }

                $preparedData[$field] = [
                    'content' => $processedContent,
                    'weight' => (float)$weight
                ];
            } else {
                $preparedData[$field] = [
                    'content' => '',
                    'weight' => (float)$weight
                ];
            }
        }

        $preparedData['content_modified_at'] = $page['content_modified_at'] ?? $page['crdate'] ?? time();

        return $preparedData;
    }

    private function getAllSubpages(int $parentId, int $depth = 0): array
    {
        $allPages = [];
        $queue = [[$parentId, 0]];

        while (!empty($queue)) {
            [$currentId, $currentDepth] = array_shift($queue);

            if ($depth !== -1 && $currentDepth > $depth) {
                continue;
            }

            $pages = $this->getSubpages($currentId);
            $allPages = array_merge($allPages, $pages);

            foreach ($pages as $page) {
                $queue[] = [$page['uid'], $currentDepth + 1];
            }
        }

        return $allPages;
    }

    protected function getSubpages(int $parentId, string $languageCode = ''): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $languageAspect = $this->context->getAspect('language');
        $languageId = $languageAspect->getId();

        $fieldsToSelect = ['uid', 'title', 'description', 'keywords', 'abstract', 'crdate', 'sys_language_uid'];
        $tableColumns = $queryBuilder->getConnection()->createSchemaManager()->listTableColumns('pages');
        $existingColumns = array_keys($tableColumns);
        $fieldsToSelect = array_intersect($fieldsToSelect, $existingColumns);

        $result = $queryBuilder
            ->select(...$fieldsToSelect)
            ->addSelectLiteral(
                '(SELECT MAX(tstamp) FROM tt_content WHERE tt_content.pid = pages.uid AND tt_content.deleted = 0 AND tt_content.hidden = 0)'
            )
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentId, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, \Doctrine\DBAL\ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($result as &$page) {
            $page['content_modified_at'] = $page['MAX(tstamp)'] ?? $page['crdate'] ?? time();
            unset($page['MAX(tstamp)']);
        }

        return $result;
    }

    private function batchGetPageContent(array $pageIds, int $languageUid): array
    {
        if (empty($pageIds)) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder('tt_content');
        $rows = $queryBuilder
            ->select('pid', 'bodytext')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($pageIds, \Doctrine\DBAL\ArrayParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, \Doctrine\DBAL\ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $contentByPage = [];
        foreach ($rows as $row) {
            $pid = (int)$row['pid'];
            $contentByPage[$pid] = trim(($contentByPage[$pid] ?? '') . ' ' . $row['bodytext']);
        }
        return $contentByPage;
    }

    protected function getPageContent(int $pageId, int $languageUid = 0): string
    {
        $queryBuilder = $this->getQueryBuilder('tt_content');

        $content = $queryBuilder
            ->select('bodytext')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('tt_content.pid', $queryBuilder->createNamedParameter($pageId, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('tt_content.hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('tt_content.deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('tt_content.sys_language_uid', $queryBuilder->createNamedParameter($languageUid, \Doctrine\DBAL\ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return implode(' ', array_column($content, 'bodytext'));
    }

    protected function getWeightedWords(array $pageData): array
    {
        $weightedWords = [];

        foreach ($this->settings['analyzedFields'] as $field => $weight) {
            if (!isset($pageData[$field]['content']) || !is_string($pageData[$field]['content'])) {
                continue;
            }

            $words = array_count_values(str_word_count(strtolower($pageData[$field]['content']), 1));

            foreach ($words as $word => $count) {
                $weightedWords[$word] = ($weightedWords[$word] ?? 0) + ($count * $weight);
            }
        }

        return $weightedWords;
    }

    private function prepareTextForAnalysis(array $pageData): string
    {
        $texts = [];

        foreach ($this->settings['analyzedFields'] as $field => $weight) {
            if (isset($pageData[$field]['content']) && !empty($pageData[$field]['content'])) {
                $weightMultiplier = max(1, round((float)$weight));
                for ($i = 0; $i < $weightMultiplier; $i++) {
                    $texts[] = $pageData[$field]['content'];
                }
            }
        }

        return implode(' ', $texts);
    }

    private function calculateRecencyBoost(array $page1, array $page2): float
    {
        $now = time();
        $maxAge = 30 * 24 * 3600;
        $age1 = min($now - ($page1['content_modified_at'] ?? $now), $maxAge);
        $age2 = min($now - ($page2['content_modified_at'] ?? $now), $maxAge);

        $normalizedAge1 = 1 - ($age1 / $maxAge);
        $normalizedAge2 = 1 - ($age2 / $maxAge);

        return abs($normalizedAge1 - $normalizedAge2);
    }

    private function findCommonKeywords(array $page1, array $page2): array
    {
        $keywords1 = isset($page1['keywords']['content'])
            ? array_map('trim', explode(',', strtolower($page1['keywords']['content'])))
            : [];
        $keywords2 = isset($page2['keywords']['content'])
            ? array_map('trim', explode(',', strtolower($page2['keywords']['content'])))
            : [];

        return array_values(array_intersect($keywords1, $keywords2));
    }

    private function determineRelevance(float $similarity): string
    {
        if ($similarity > 0.7) {
            return 'High';
        }
        if ($similarity > 0.4) {
            return 'Medium';
        }
        return 'Low';
    }
}
