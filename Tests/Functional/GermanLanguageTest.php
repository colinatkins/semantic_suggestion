<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use Cywolf\NlpTools\Service\LanguageDetectionService;
use Cywolf\NlpTools\Service\TextAnalysisService;
use Cywolf\NlpTools\Service\TextVectorizerService;
use Cywolf\NlpTools\Service\StopWordsFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GermanLanguageTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/semantic_suggestion',
        'typo3conf/ext/nlp_tools'
    ];

    protected PageAnalysisService $pageAnalysisService;
    protected LanguageDetectionService $languageDetector;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->languageDetector = GeneralUtility::makeInstance(LanguageDetectionService::class);
        
        // Note: Dans un vrai test, il faudrait injecter tous les services correctement
        // Ceci est un exemple simplifié
    }

    /**
     * @test
     */
    public function testGermanLanguageDetection(): void
    {
        $germanText = 'Das ist ein deutscher Text über die Automobilindustrie. 
                       Deutschland ist bekannt für seine hochwertigen Autos wie BMW, Mercedes-Benz und Volkswagen.
                       Die deutsche Ingenieurskunst ist weltweit anerkannt.';
        
        $detectedLanguage = $this->languageDetector->detectLanguage($germanText);
        
        self::assertEquals('de', $detectedLanguage, 'German language should be detected correctly');
    }
    
    /**
     * @test
     */
    public function testGermanTextAnalysis(): void
    {
        $stopWordsFactory = GeneralUtility::makeInstance(StopWordsFactory::class);
        $textAnalyzer = GeneralUtility::makeInstance(TextAnalysisService::class, 
            $this->languageDetector, 
            $stopWordsFactory
        );
        
        $germanText = 'Die deutsche Automobilindustrie ist sehr wichtig für die Wirtschaft.';
        
        // Test stop words removal
        $processedText = $textAnalyzer->removeStopWords($germanText, 'de');
        self::assertStringNotContainsString('die', strtolower($processedText));
        self::assertStringNotContainsString('ist', strtolower($processedText));
        self::assertStringContainsString('automobilindustrie', strtolower($processedText));
        
        // Test stemming
        $stemmedWords = $textAnalyzer->stem($germanText, 'de');
        self::assertIsArray($stemmedWords);
        self::assertNotEmpty($stemmedWords);
    }
    
    /**
     * @test
     */
    public function testGermanTfIdfVectorization(): void
    {
        $stopWordsFactory = GeneralUtility::makeInstance(StopWordsFactory::class);
        $textAnalyzer = GeneralUtility::makeInstance(TextAnalysisService::class, 
            $this->languageDetector, 
            $stopWordsFactory
        );
        $textVectorizer = GeneralUtility::makeInstance(TextVectorizerService::class,
            $textAnalyzer,
            $this->languageDetector,
            $stopWordsFactory
        );
        
        $germanTexts = [
            'Die deutsche Automobilindustrie produziert hochwertige Fahrzeuge.',
            'BMW und Mercedes sind bekannte deutsche Automarken.',
            'Die Technologie in deutschen Autos ist sehr fortschrittlich.'
        ];
        
        $tfidfResult = $textVectorizer->createTfIdfVectors($germanTexts, 'de');
        
        self::assertIsArray($tfidfResult);
        self::assertArrayHasKey('vectors', $tfidfResult);
        self::assertArrayHasKey('vocabulary', $tfidfResult);
        self::assertCount(3, $tfidfResult['vectors']);
        
        // Test similarity calculation
        $similarity = $textVectorizer->cosineSimilarity(
            $tfidfResult['vectors'][0],
            $tfidfResult['vectors'][1]
        );
        
        self::assertIsFloat($similarity);
        self::assertGreaterThan(0, $similarity);
        self::assertLessThanOrEqual(1, $similarity);
    }
    
    /**
     * Test pour s'assurer que le système fonctionne bien avec les umlauts allemands
     * @test
     */
    public function testGermanUmlautsHandling(): void
    {
        $textWithUmlauts = 'Über die schöne Landschaft in Österreich und ihre Größe.';
        
        $detectedLanguage = $this->languageDetector->detectLanguage($textWithUmlauts);
        self::assertEquals('de', $detectedLanguage);
        
        $stopWordsFactory = GeneralUtility::makeInstance(StopWordsFactory::class);
        $textAnalyzer = GeneralUtility::makeInstance(TextAnalysisService::class, 
            $this->languageDetector, 
            $stopWordsFactory
        );
        
        $processedText = $textAnalyzer->removeStopWords($textWithUmlauts, 'de');
        
        // Vérifier que les mots avec umlauts sont préservés
        self::assertStringContainsString('schöne', strtolower($processedText));
        self::assertStringContainsString('österreich', strtolower($processedText));
        self::assertStringContainsString('größe', strtolower($processedText));
    }
}