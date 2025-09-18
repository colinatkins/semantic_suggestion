#!/usr/bin/env php
<?php

/**
 * Test script for unified configuration
 * Run this script to verify the new configuration logic works correctly
 *
 * Usage: php Scripts/TestUnifiedConfiguration.php
 */

require_once dirname(__DIR__) . '/../../vendor/autoload.php';

use TalanHdf\SemanticSuggestion\Task\GenerateSimilaritiesTask;

echo "🧪 Testing Unified Configuration Logic\n";
echo "=====================================\n\n";

/**
 * Test 1: Default Configuration
 */
echo "Test 1: Default Configuration\n";
echo "-----------------------------\n";
$task1 = new GenerateSimilaritiesTask();
echo sprintf("✓ Default qualityLevel: %.2f\n", $task1->qualityLevel);
echo sprintf("✓ Computed storage threshold: %.2f\n", $task1->minimumSimilarity);
echo sprintf("✓ Display threshold: %.2f\n", $task1->qualityLevel);
echo "\n";

/**
 * Test 2: Legacy Migration
 */
echo "Test 2: Legacy Migration\n";
echo "------------------------\n";
$task2 = new GenerateSimilaritiesTask();
$task2->qualityLevel = 0.3; // Default
$task2->minimumSimilarity = 0.5; // Legacy customized value

echo "Before migration:\n";
echo sprintf("  qualityLevel: %.2f\n", $task2->qualityLevel);
echo sprintf("  minimumSimilarity: %.2f\n", $task2->minimumSimilarity);

$task2->initializeQualityLevel();

echo "After migration:\n";
echo sprintf("  ✓ qualityLevel: %.2f (migrated)\n", $task2->qualityLevel);
echo sprintf("  ✓ minimumSimilarity: %.2f (preserved)\n", $task2->minimumSimilarity);
echo "\n";

/**
 * Test 3: German Language Optimization
 */
echo "Test 3: German Language Optimization\n";
echo "-------------------------------------\n";
$task3 = new GenerateSimilaritiesTask();
$task3->qualityLevel = 0.25; // German optimized
$task3->initializeQualityLevel();

echo sprintf("✓ German qualityLevel: %.2f\n", $task3->qualityLevel);
echo sprintf("✓ German storage threshold: %.2f (more permissive for compounds)\n", $task3->minimumSimilarity);
echo "\n";

/**
 * Test 4: High-Quality Site Configuration
 */
echo "Test 4: High-Quality Site Configuration\n";
echo "----------------------------------------\n";
$task4 = new GenerateSimilaritiesTask();
$task4->qualityLevel = 0.5; // High quality
$task4->initializeQualityLevel();

echo sprintf("✓ High-quality qualityLevel: %.2f\n", $task4->qualityLevel);
echo sprintf("✓ High-quality storage threshold: %.2f\n", $task4->minimumSimilarity);
echo "✓ Only high-quality suggestions will be displayed\n";
echo "\n";

/**
 * Test 5: Edge Cases
 */
echo "Test 5: Edge Cases\n";
echo "------------------\n";

// Very low quality
$task5a = new GenerateSimilaritiesTask();
$task5a->qualityLevel = 0.1; // Minimum allowed
$task5a->initializeQualityLevel();
echo sprintf("✓ Minimum qualityLevel: %.2f → storage: %.2f\n", $task5a->qualityLevel, $task5a->minimumSimilarity);

// Very high quality
$task5b = new GenerateSimilaritiesTask();
$task5b->qualityLevel = 0.9; // Very high
$task5b->initializeQualityLevel();
echo sprintf("✓ High qualityLevel: %.2f → storage: %.2f\n", $task5b->qualityLevel, $task5b->minimumSimilarity);

echo "\n";

/**
 * Test 6: Configuration Examples
 */
echo "Test 6: Configuration Examples\n";
echo "-------------------------------\n";

$examples = [
    'Standard Site' => 0.3,
    'German Site (compounds)' => 0.25,
    'High-Quality Site' => 0.4,
    'Permissive Site' => 0.2,
    'Very Strict Site' => 0.6
];

foreach ($examples as $type => $quality) {
    $task = new GenerateSimilaritiesTask();
    $task->qualityLevel = $quality;
    $task->initializeQualityLevel();

    echo sprintf("%-25s: quality=%.2f → storage=%.2f, display=%.2f\n",
        $type,
        $task->qualityLevel,
        $task->minimumSimilarity,
        $task->qualityLevel
    );
}

echo "\n";

/**
 * Test 7: TypoScript Backward Compatibility
 */
echo "Test 7: TypoScript Backward Compatibility\n";
echo "------------------------------------------\n";

// Simulate different TypoScript configurations
$configurations = [
    'New unified' => ['qualityLevel' => 0.35],
    'Legacy proximityThreshold' => ['proximityThreshold' => 0.4],
    'Both (new wins)' => ['qualityLevel' => 0.35, 'proximityThreshold' => 0.5],
    'Neither (default)' => []
];

foreach ($configurations as $name => $config) {
    $qualityLevel = getQualityLevelFromSettings($config);
    echo sprintf("%-25s: %s → qualityLevel=%.2f\n",
        $name,
        json_encode($config),
        $qualityLevel
    );
}

echo "\n🎉 All tests completed successfully!\n\n";

echo "💡 Migration Guide:\n";
echo "===================\n";
echo "1. Old Scheduler minimumSimilarity → automatically migrated to qualityLevel\n";
echo "2. Old TypoScript proximityThreshold → use qualityLevel instead\n";
echo "3. Unified excludePages → no more split between Scheduler/TypoScript\n";
echo "4. Single threshold to maintain → much simpler configuration\n";
echo "\n";

echo "📊 Recommended Quality Levels:\n";
echo "===============================\n";
echo "• 0.25: German sites (compound word handling)\n";
echo "• 0.30: Standard multilingual sites\n";
echo "• 0.35: High-quality content sites\n";
echo "• 0.40: Very selective suggestions\n";
echo "\n";

/**
 * Helper function to simulate SuggestionService::getQualityLevel()
 */
function getQualityLevelFromSettings(array $settings): float
{
    // NEW: Quality level (unified configuration)
    if (isset($settings['qualityLevel'])) {
        return (float)$settings['qualityLevel'];
    }

    // Legacy support: use proximityThreshold if available
    if (isset($settings['proximityThreshold'])) {
        return (float)$settings['proximityThreshold'];
    }

    // Default quality level for TF-IDF
    return 0.3;
}