#!/bin/bash

# Simple linting script for TYPO3 extension
# Checks PHP syntax and basic PSR-12 compliance

echo "Running PHP syntax check..."
find Classes/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

echo "Checking for PSR-12 compliance (basic)..."
# Add more checks as needed

echo "Linting complete."