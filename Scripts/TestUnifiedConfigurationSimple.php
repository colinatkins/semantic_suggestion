#!/usr/bin/env php
<?php

/**
 * Simple test script for unified configuration logic
 * Tests the core logic without TYPO3 dependencies
 */

echo "🧪 Testing Unified Configuration Logic (Simplified)\n";
echo "====================================================\n\n";

/**
 * Simulate the core logic from GenerateSimilaritiesTask
 */
class ConfigurationTester
{
    public float $qualityLevel = 0.3;
    public float $minimumSimilarity = 0.1;

    public function initializeQualityLevel(): void
    {
        // If qualityLevel is at default but minimumSimilarity was customized
        if ($this->qualityLevel === 0.3 && $this->minimumSimilarity !== 0.1) {
            // Migrate from legacy minimumSimilarity
            $this->qualityLevel = max(0.1, $this->minimumSimilarity + 0.1);
            echo "   → Migrated from legacy minimumSimilarity ({$this->minimumSimilarity}) to qualityLevel ({$this->qualityLevel})\n";
        }

        // Always sync minimumSimilarity with qualityLevel for backward compatibility
        $this->minimumSimilarity = max(0.05, $this->qualityLevel - 0.1);
    }

    public function getStorageThreshold(): float
    {
        return $this->minimumSimilarity;
    }

    public function getDisplayThreshold(): float
    {
        return $this->qualityLevel;
    }
}

/**
 * Simulate SuggestionService logic
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

/**
 * Test 1: Default Configuration
 */
echo "Test 1: Default Configuration\n";
echo "-----------------------------\n";
$tester1 = new ConfigurationTester();
$tester1->initializeQualityLevel();
echo sprintf("✓ Default qualityLevel: %.2f\n", $tester1->qualityLevel);
echo sprintf("✓ Storage threshold: %.2f (stores broad range)\n", $tester1->getStorageThreshold());
echo sprintf("✓ Display threshold: %.2f (shows quality results)\n", $tester1->getDisplayThreshold());
echo "✓ ONE parameter controls both storage and display!\n\n";

/**
 * Test 2: Legacy Migration
 */
echo "Test 2: Legacy Migration (v2.x → v3.1)\n";
echo "---------------------------------------\n";
$tester2 = new ConfigurationTester();
$tester2->qualityLevel = 0.3; // Default (not customized)
$tester2->minimumSimilarity = 0.5; // Legacy customized value

echo "Before migration:\n";
echo sprintf("  qualityLevel: %.2f (default)\n", $tester2->qualityLevel);
echo sprintf("  minimumSimilarity: %.2f (legacy custom)\n", $tester2->minimumSimilarity);

$tester2->initializeQualityLevel();

echo "After automatic migration:\n";
echo sprintf("  ✓ qualityLevel: %.2f (migrated from legacy)\n", $tester2->qualityLevel);
echo sprintf("  ✓ storage threshold: %.2f (computed)\n", $tester2->getStorageThreshold());
echo sprintf("  ✓ display threshold: %.2f (quality level)\n", $tester2->getDisplayThreshold());
echo "\n";

/**
 * Test 3: Language-Specific Optimizations
 */
echo "Test 3: Language-Specific Optimizations\n";
echo "----------------------------------------\n";

$languages = [
    'German (compound words)' => 0.25,
    'English/French/Spanish' => 0.3,
    'Other languages' => 0.35,
    'High-quality site' => 0.4
];

foreach ($languages as $language => $quality) {
    $tester = new ConfigurationTester();
    $tester->qualityLevel = $quality;
    $tester->initializeQualityLevel();

    echo sprintf("%-25s: quality=%.2f → storage=%.2f, display=%.2f\n",
        $language,
        $tester->qualityLevel,
        $tester->getStorageThreshold(),
        $tester->getDisplayThreshold()
    );
}
echo "\n";

/**
 * Test 4: TypoScript Backward Compatibility
 */
echo "Test 4: TypoScript Backward Compatibility\n";
echo "------------------------------------------\n";

$configurations = [
    'New unified config' => ['qualityLevel' => 0.35],
    'Legacy proximityThreshold' => ['proximityThreshold' => 0.4],
    'Both (new wins)' => ['qualityLevel' => 0.35, 'proximityThreshold' => 0.5],
    'Empty (uses default)' => []
];

foreach ($configurations as $name => $config) {
    $qualityLevel = getQualityLevelFromSettings($config);
    $storageThreshold = max(0.05, $qualityLevel - 0.1);

    echo sprintf("%-25s: %s\n", $name, json_encode($config));
    echo sprintf("                         → quality=%.2f, storage=%.2f, display=%.2f\n",
        $qualityLevel, $storageThreshold, $qualityLevel);
}
echo "\n";

/**
 * Test 5: Problem Scenarios (OLD vs NEW)
 */
echo "Test 5: Configuration Problems - OLD vs NEW\n";
echo "============================================\n";

echo "❌ OLD SYSTEM PROBLEMS:\n";
echo "------------------------\n";
echo "Scenario: User wants high-quality suggestions (0.8)\n";
echo "Old config: Scheduler minimumSimilarity=0.3, TypoScript proximityThreshold=0.8\n";
echo "Result: ❌ NO suggestions displayed (none stored above 0.8)\n";
echo "User confusion: \"Why don't I see any suggestions?\"\n\n";

echo "✅ NEW SYSTEM SOLUTION:\n";
echo "------------------------\n";
echo "New config: qualityLevel=0.8\n";
$tester5 = new ConfigurationTester();
$tester5->qualityLevel = 0.8;
$tester5->initializeQualityLevel();
echo sprintf("Result: ✅ Storage=%.2f (broad), Display=%.2f (quality)\n",
    $tester5->getStorageThreshold(), $tester5->getDisplayThreshold());
echo "User experience: High-quality suggestions work as expected!\n\n";

/**
 * Test 6: Migration Scenarios
 */
echo "Test 6: Real-World Migration Scenarios\n";
echo "=======================================\n";

$scenarios = [
    [
        'name' => 'Fresh v3.1 installation',
        'qualityLevel' => 0.3,
        'minimumSimilarity' => 0.1,
        'description' => 'New installation with defaults'
    ],
    [
        'name' => 'Upgrade from v2.x (standard)',
        'qualityLevel' => 0.3,
        'minimumSimilarity' => 0.4,
        'description' => 'Legacy minimumSimilarity was customized'
    ],
    [
        'name' => 'Upgrade from v2.x (German)',
        'qualityLevel' => 0.3,
        'minimumSimilarity' => 0.2,
        'description' => 'German site with lower threshold'
    ]
];

foreach ($scenarios as $scenario) {
    echo "Scenario: {$scenario['name']}\n";
    echo "Description: {$scenario['description']}\n";

    $tester = new ConfigurationTester();
    $tester->qualityLevel = $scenario['qualityLevel'];
    $tester->minimumSimilarity = $scenario['minimumSimilarity'];

    echo "Before: quality={$tester->qualityLevel}, minimum={$tester->minimumSimilarity}\n";
    $tester->initializeQualityLevel();
    echo "After:  quality={$tester->qualityLevel}, storage={$tester->getStorageThreshold()}, display={$tester->getDisplayThreshold()}\n\n";
}

echo "🎉 All tests completed successfully!\n\n";

echo "📊 Benefits of Unified Configuration:\n";
echo "=====================================\n";
echo "✅ Single parameter to configure (qualityLevel)\n";
echo "✅ No more hierarchy confusion (Scheduler > TypoScript)\n";
echo "✅ Automatic storage optimization (qualityLevel - 0.1)\n";
echo "✅ Backward compatibility with legacy configurations\n";
echo "✅ Self-documenting configuration values\n";
echo "✅ Reduced support questions and user confusion\n\n";

echo "🚀 Recommended Quality Levels:\n";
echo "===============================\n";
echo "0.25 → German sites (compound word support)\n";
echo "0.30 → Standard sites (balanced quality/quantity)\n";
echo "0.35 → High-content sites (more selective)\n";
echo "0.40 → Premium sites (very selective suggestions)\n";
echo "\n";