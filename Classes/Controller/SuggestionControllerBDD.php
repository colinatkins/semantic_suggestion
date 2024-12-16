<?php
namespace TalanHdf\SemanticSuggestion\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;

class SuggestionsController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_PAGE = 10;

    protected PageRepository $pageRepository;
    protected FileRepository $fileRepository;
    protected array $debugLogs = [];

    public function __construct(
        PageRepository $pageRepository,
        FileRepository $fileRepository
    ) {
        $this->pageRepository = $pageRepository;
        $this->fileRepository = $fileRepository;
        $this->setLogger(GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__));
    }

    private function logDebug(string $message, array $context = []): void
    {
        // On vérifie si le mode debug est activé
        $debugMode = $this->settings['debugMode'] ?? false;
        if ($debugMode && $this->logger instanceof LoggerInterface) {
            $this->logger->debug($message, $context);
            $this->debugLogs[] = ['message' => $message, 'context' => $context];
        }
    }

    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
    {
        $this->logDebug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);

        $currentPageId = $GLOBALS['TSFE']->id;
        $currentLanguageUid = $this->getCurrentLanguageUid();
        $viewData = $this->generateSuggestionsFromDatabase($currentPageId, $currentPage, $itemsPerPage);
        
        // Ajouter les logs de debug aux données de la vue si nécessaire
        $viewData['debugLogs'] = $this->debugLogs;
        
        $this->view->assignMultiple($viewData);
        return $this->htmlResponse();
    }

    protected function generateSuggestionsFromDatabase(int $currentPageId, int $currentPage, int $itemsPerPage): array
    {
        $this->logDebug('Generating suggestions from database', [
            'pageId' => $currentPageId,
            'currentPage' => $currentPage,
            'itemsPerPage' => $itemsPerPage
        ]);

        $proximityThreshold = (float)($this->settings['proximityThreshold'] ?? 0.3);
        $maxSuggestions = (int)($this->settings['maxSuggestions'] ?? 3);
        $currentLanguageUid = $this->getCurrentLanguageUid();
        $excludePages = GeneralUtility::intExplode(',', $this->settings['excludePages'] ?? '', true);

        $suggestions = $this->findSimilarPagesFromDatabase(
            $currentPageId,
            $proximityThreshold,
            $excludePages,
            $currentLanguageUid,
            $maxSuggestions
        );

        // Implémenter la pagination
        $paginator = new ArrayPaginator($suggestions, $currentPage, $itemsPerPage);
        $paginatedSuggestions = [];
        foreach ($paginator->getPaginatedItems() as $pageId => $suggestion) {
            $paginatedSuggestions[$pageId] = $suggestion;
        }

        $pagination = [
            'currentPage' => $currentPage,
            'numberOfPages' => $paginator->getNumberOfPages(),
            'hasNextPage' => $paginator->getCurrentPageNumber() < $paginator->getNumberOfPages(),
            'hasPreviousPage' => $paginator->getCurrentPageNumber() > 1,
            'startRecord' => ($currentPage - 1) * $itemsPerPage + 1,
            'endRecord' => min($currentPage * $itemsPerPage, count($suggestions)),
            'totalItems' => count($suggestions)
        ];

        $this->logDebug('Suggestions generated', [
            'count' => count($paginatedSuggestions),
            'totalItems' => $pagination['totalItems']
        ]);

        return [
            'currentPageTitle' => $this->pageRepository->getPage($currentPageId)['title'] ?? 'Current Page',
            'suggestions' => $paginatedSuggestions,
            'pagination' => $pagination
        ];
    }

    protected function findSimilarPagesFromDatabase(
        int $currentPageId,
        float $threshold,
        array $excludePages,
        int $currentLanguageUid,
        int $maxSuggestions
    ): array {
        $this->logDebug('Finding similar pages from database', [
            'currentPageId' => $currentPageId,
            'threshold' => $threshold,
            'languageUid' => $currentLanguageUid,
            'maxSuggestions' => $maxSuggestions
        ]);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_semanticsuggestion_similarities');

        $similarities = $queryBuilder
            ->select('*')
            ->from('tx_semanticsuggestion_similarities')
            ->where(
                $queryBuilder->expr()->eq('page_id', $queryBuilder->createNamedParameter($currentPageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->gte('similarity_score', $queryBuilder->createNamedParameter($threshold, \PDO::PARAM_FLOAT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($currentLanguageUid, \PDO::PARAM_INT))
            )
            ->orderBy('similarity_score', 'DESC')
            ->setMaxResults($maxSuggestions)
            ->executeQuery()
            ->fetchAllAssociative();

        $this->logDebug('Database query results', [
            'count' => count($similarities),
            'firstResult' => $similarities[0] ?? null
        ]);

        $suggestions = [];
        foreach ($similarities as $similarity) {
            $similarPageId = (int)$similarity['similar_page_id'];
            
            if (in_array($similarPageId, $excludePages)) {
                $this->logDebug('Page excluded', ['pageId' => $similarPageId]);
                continue;
            }

            $pageData = $this->pageRepository->getPage($similarPageId);
            if (!$pageData) {
                $this->logDebug('Page not found', ['pageId' => $similarPageId]);
                continue;
            }

            $excerpt = $this->prepareExcerpt($pageData, (int)($this->settings['excerptLength'] ?? 150));
            $suggestions[$similarPageId] = [
                'similarity' => $similarity['similarity_score'],
                'data' => $pageData,
                'excerpt' => $excerpt,
                'media' => $this->getPageMedia($similarPageId),
            ];
        }

        $this->logDebug('Suggestions prepared', ['count' => count($suggestions)]);
        return $suggestions;
    }

    protected function prepareExcerpt(array $pageData, int $excerptLength): string
    {
        $sources = GeneralUtility::trimExplode(',', $this->settings['excerptSources'] ?? 'bodytext,description,abstract', true);
        
        foreach ($sources as $source) {
            $content = $source === 'bodytext' ? ($pageData['tt_content'] ?? '') : ($pageData[$source] ?? '');
            
            if (!empty($content)) {
                $content = strip_tags($content);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                return mb_substr($content, 0, $excerptLength) . (mb_strlen($content) > $excerptLength ? '...' : '');
            }
        }
        
        return '';
    }

    protected function getPageMedia(int $pageId)
    {
        $fileObjects = $this->fileRepository->findByRelation('pages', 'media', $pageId);
        return !empty($fileObjects) ? $fileObjects[0] : null;
    }

    protected function getCurrentLanguageUid(): int
    {
        return GeneralUtility::makeInstance(Context::class)->getAspect('language')->getId();
    }
}