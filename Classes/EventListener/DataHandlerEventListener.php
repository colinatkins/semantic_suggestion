<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Event listener for DataHandler operations.
 * Replaces the deprecated SC_OPTIONS hooks for TYPO3 14 compatibility.
 * Clears semantic suggestion cache when content is modified.
 */
final class DataHandlerEventListener
{
    public function __construct(
        private readonly CacheManager $cacheManager
    ) {}

    /**
     * Called after all DataHandler datamap operations.
     * Clears the semantic suggestion cache to ensure suggestions stay up-to-date.
     */
    #[AsEventListener(
        identifier: 'semantic-suggestion/after-datamap-operations',
        event: \TYPO3\CMS\Core\DataHandling\Event\AfterDatamapOperationEvent::class
    )]
    public function afterDatamapOperation(): void
    {
        $this->clearSemanticSuggestionCache();
    }

    /**
     * Called after all DataHandler cmdmap operations (copy, move, delete).
     */
    #[AsEventListener(
        identifier: 'semantic-suggestion/after-cmdmap-operations',
        event: \TYPO3\CMS\Core\DataHandling\Event\AfterCommandmapOperationEvent::class
    )]
    public function afterCommandmapOperation(): void
    {
        $this->clearSemanticSuggestionCache();
    }

    /**
     * Clears the cache associated with semantic suggestions.
     * Flushes caches tagged with 'tx_semanticsuggestion'.
     */
    private function clearSemanticSuggestionCache(): void
    {
        $this->cacheManager->flushCachesByTag('tx_semanticsuggestion');
    }
}
