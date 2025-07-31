<?php

declare(strict_types=1);

namespace TalanHdf\SemanticSuggestion\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TalanHdf\SemanticSuggestion\Service\PageAnalysisService;
use TalanHdf\SemanticSuggestion\Service\SiteLanguageService;
use Cywolf\NlpTools\Service\LanguageDetectionService;
use Cywolf\NlpTools\Service\TextAnalysisService;
use Cywolf\NlpTools\Service\TextVectorizerService;

class DiagnosticCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Diagnostic command for German language detection and processing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🇩🇪 German Language Diagnostic');

        // Test 1: Check if nlp_tools services are available
        $io->section('1. NLP Tools Services Availability');
        
        try {
            $languageDetector = GeneralUtility::makeInstance(LanguageDetectionService::class);
            $io->success('✅ LanguageDetectionService: Available');
        } catch (\Exception $e) {
            $io->error('❌ LanguageDetectionService: ' . $e->getMessage());
            return Command::FAILURE;
        }

        try {
            $textAnalyzer = GeneralUtility::makeInstance(TextAnalysisService::class);
            $io->success('✅ TextAnalysisService: Available');
        } catch (\Exception $e) {
            $io->error('❌ TextAnalysisService: ' . $e->getMessage());
            return Command::FAILURE;
        }

        try {
            $textVectorizer = GeneralUtility::makeInstance(TextVectorizerService::class);
            $io->success('✅ TextVectorizerService: Available');
        } catch (\Exception $e) {
            $io->error('❌ TextVectorizerService: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 2: German language detection
        $io->section('2. German Language Detection Test');
        
        $germanSamples = [
            'Die deutsche Automobilindustrie ist sehr wichtig für die Wirtschaft.',
            'BMW und Mercedes-Benz sind bekannte deutsche Automarken.',
            'Deutschland exportiert viele hochwertige Fahrzeuge in die ganze Welt.',
            'Über die schöne Landschaft in Österreich und ihre Größe.',
        ];

        foreach ($germanSamples as $index => $text) {
            $detectedLang = $languageDetector->detectLanguage($text);
            $status = $detectedLang === 'de' ? '✅' : '❌';
            $io->writeln("Sample " . ($index + 1) . ": $status Detected: '$detectedLang' | Text: " . substr($text, 0, 50) . "...");
        }

        // Test 3: German text processing
        $io->section('3. German Text Processing Test');
        
        $germanText = 'Die deutschen Automobilhersteller produzieren hochwertige Fahrzeuge für den internationalen Markt.';
        $io->writeln("Original: $germanText");
        
        $processedText = $textAnalyzer->removeStopWords($germanText, 'de');
        $io->writeln("After stop words removal: $processedText");
        
        $stemmedWords = $textAnalyzer->stem($processedText, 'de');
        $io->writeln("After stemming: " . implode(' ', $stemmedWords));

        // Test 4: Check German pages in database
        $io->section('4. German Pages in Database');
        
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('pages');
        
        $germanPages = $queryBuilder
            ->select('uid', 'title', 'sys_language_uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
            )
            ->setMaxResults(10)
            ->executeQuery()
            ->fetchAllAssociative();

        $io->writeln("Found " . count($germanPages) . " German pages (sys_language_uid=1):");
        foreach ($germanPages as $page) {
            $io->writeln("- ID: {$page['uid']} | Title: {$page['title']}");
        }

        // Test 5: Check similarities in database
        $io->section('5. German Similarities in Database');
        
        $queryBuilder = $connectionPool->getQueryBuilderForTable('tx_semanticsuggestion_similarities');
        $germanSimilarities = $queryBuilder
            ->select('*')
            ->from('tx_semanticsuggestion_similarities')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER))
            )
            ->setMaxResults(10)
            ->executeQuery()
            ->fetchAllAssociative();

        $io->writeln("Found " . count($germanSimilarities) . " German similarities in database:");
        foreach ($germanSimilarities as $similarity) {
            $io->writeln("- Page {$similarity['page_id']} ↔ Page {$similarity['similar_page_id']} | Score: {$similarity['similarity_score']}");
        }

        // Test 6: TF-IDF test with German texts
        $io->section('6. TF-IDF Processing Test');
        
        $germanTexts = [
            'Die deutsche Automobilindustrie produziert hochwertige Fahrzeuge.',
            'BMW und Mercedes sind bekannte deutsche Automarken.',
            'Die Technologie in deutschen Autos ist sehr fortschrittlich.'
        ];

        try {
            $tfidfResult = $textVectorizer->createTfIdfVectors($germanTexts, 'de');
            $io->success("✅ TF-IDF vectors created successfully");
            $io->writeln("Vocabulary size: " . count($tfidfResult['vocabulary']));
            $io->writeln("Vector count: " . count($tfidfResult['vectors']));
            
            if (count($tfidfResult['vectors']) >= 2) {
                $similarity = $textVectorizer->cosineSimilarity($tfidfResult['vectors'][0], $tfidfResult['vectors'][1]);
                $io->writeln("Similarity between text 1 and 2: " . round($similarity, 4));
            }
        } catch (\Exception $e) {
            $io->error("❌ TF-IDF processing failed: " . $e->getMessage());
        }

        // Test 7: SiteLanguageService automatic language detection
        $io->section('7. SiteLanguageService Automatic Detection Test');
        
        try {
            $siteLanguageService = GeneralUtility::makeInstance(SiteLanguageService::class);
            $io->success('✅ SiteLanguageService: Available');
            
            // Test avec des pages allemandes simulées
            foreach ($germanPages as $page) {
                $detectedLang = $siteLanguageService->detectLanguageForPage($page, $languageDetector);
                $status = $detectedLang === 'de' ? '✅' : '❌';
                $io->writeln("Page {$page['uid']}: $status Detected: '$detectedLang' | sys_language_uid: {$page['sys_language_uid']}");
            }
            
            // Test toutes les langues du site
            $allLanguages = $siteLanguageService->getAllSiteLanguages();
            $io->writeln("Site languages detected: " . count($allLanguages));
            foreach ($allLanguages as $lang) {
                $io->writeln("- UID {$lang['uid']}: {$lang['code']} ({$lang['title']}) - {$lang['locale']}");
            }
            
        } catch (\Exception $e) {
            $io->error('❌ SiteLanguageService test failed: ' . $e->getMessage());
        }

        $io->success('🎉 Enhanced diagnostic complete! The system now uses automatic TYPO3 site configuration for language detection.');
        return Command::SUCCESS;
    }
}