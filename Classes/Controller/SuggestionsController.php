<?php
namespace TalanHdf\SemanticSuggestion\Controller;

use Psr\Http\Message\ResponseInterface;
use TalanHdf\SemanticSuggestion\Service\SuggestionService;
use TalanHdf\SemanticSuggestion\Service\UtilityService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Core\Cache\CacheManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use Psr\Log\LoggerInterface;


class SuggestionsController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_PAGE = 10;

    public function __construct(
        protected PageAnalysisService $pageAnalysisService,
        protected SuggestionService $suggestionService,
        protected UtilityService $utility
    ) {
        $this->setLogger(GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__));
    }

    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
    {
        $this->utility->logDebug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);

        $currentPageId = $GLOBALS['TSFE']->id;
        $currentLanguageUid = $this->utility->getCurrentLanguageUid();
        $viewData = $this->suggestionService->generateSuggestionsFromDatabase($currentPageId, $currentPage, $itemsPerPage);
        // Ajouter les logs de debug aux données de la vue si nécessaire
        $viewData['debugLogs'] = $this->utility->getDebugLogs();

        $this->view->assignMultiple($viewData);
        return $this->htmlResponse();
    }

//    // old listAction
//    public function listAction(int $currentPage = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): ResponseInterface
//    {
//        $this->utility->logDebug('listAction called', ['currentPage' => $currentPage, 'itemsPerPage' => $itemsPerPage]);
//
//        $currentPageId = $GLOBALS['TSFE']->id;
//        $currentLanguageUid = $this->utility->getCurrentLanguageUid();
//
//        $cacheIdentifier = 'suggestions_' . $currentPageId . '_' . $currentLanguageUid . '_' . $currentPage . '_' . $itemsPerPage;
//        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
//        $cache = $cacheManager->getCache('semantic_suggestion');
//
//        try {
//            if ($cache->has($cacheIdentifier)) {
//                $this->utility->logDebug('Cache hit for suggestions', ['pageId' => $currentPageId, 'currentPage' => $currentPage, 'languageUid' => $currentLanguageUid]);
//                $viewData = $cache->get($cacheIdentifier);
//            } else {
//                $this->utility->logDebug('Cache miss for suggestions', ['pageId' => $currentPageId, 'currentPage' => $currentPage, 'languageUid' => $currentLanguageUid]);
//                $viewData = $this->suggestionService->generateSuggestions($this->settings, $currentPageId, $currentPage, $itemsPerPage, $currentLanguageUid);
//
//                if (!empty($viewData['suggestions'])) {
//                    $cache->set($cacheIdentifier, $viewData, ['tx_semanticsuggestion'], 3600);
//                } else {
//                    $this->utility->logDebug('No suggestions generated', ['pageId' => $currentPageId, 'currentPage' => $currentPage, 'languageUid' => $currentLanguageUid]);
//                }
//            }
//            dd($viewData);
//
//            $viewData['debugLogs'] = $this->utility->getDebugLogs();
//            $this->view->assignMultiple($viewData);
//
//        } catch (\Exception $e) {
//            $this->logger->error('Error in listAction', ['exception' => $e->getMessage()]);
//            $this->view->assign('error', 'An error occurred while generating suggestions.');
//        }
//
//        return $this->htmlResponse();
//    }
}