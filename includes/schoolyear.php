<?php
/**
 * School Year Management Functions
 * Handle school year CRUD operations and validation
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4
 */

// Ensure required files are loaded
require_once __DIR__ . '/db.php';

/**
 * Validate school year format
 * Format must be YYYY-YYYY where second year equals first year plus one
 * 
 * @param string $name School year name to validate
 * @return bool True if valid format
 * 
 * Requirements: 1.2
 */
function validateSchoolYearFormat(string $name): bool
{
    // Check basic format: YYYY-YYYY (9 characters with dash in middle)
    if (!preg_match('/^(\d{4})-(\d{4})$/', $name, $matches)) {
        return false;
    }
    
    $firstYear = (int) $matches[1];
    $secondYear = (int) $matches[2];
    
    // Second year must equal first year plus one
    if ($secondYear !== $firstYear + 1) {
        return false;
    }
    
    // Reasonable year range (1900-2100)
    if ($firstYear < 1900 || $firstYear > 2100) {
        return false;
    }
    
    return true;
}

/**
 * Get all school years
 * 
 * @return array List of school years with id, name, is_active, start_date, end_date, created_at
 * 
 * Requirements: 1.1
 */
function getAllSchoolYears(): array
{
    $sql = "SELECT id, name, is_active, start_date, end_date, created_at, updated_at 
            FROM school_years 
            ORDER BY name DESC";
    
    return dbFetchAll($sql);
}

/**
 * Get the currently active school year
 * 
 * @return array|null School year record or null if none active
 * 
 * Requirements: 1.3, 1.4
 */
function getActiveSchoolYear(): ?array
{
    $sql = "SELECT id, name, is_active, start_date, end_date, created_at, updated_at 
            FROM school_years 
            WHERE is_active = 1 
            LIMIT 1";
    
    return dbFetchOne($sql);
}

/**
 * Create a new school year
 * 
 * @param string $name School year name (e.g., "2024-2025")
 * @param string|null $startDate Optional start date
 * @param string|null $endDate Optional end date
 * @return int|false New school year ID or false on failure
 * 
 * Requirements: 1.2
 */
function createSchoolYear(string $name, ?string $startDate = null, ?string $endDate = null): int|false
{
    // Validate format
    if (!validateSchoolYearFormat($name)) {
        return false;
    }
    
    // Check for duplicate
    $existingSql = "SELECT id FROM school_years WHERE name = ?";
    $existing = dbFetchOne($existingSql, [$name]);
    
    if ($existing) {
        return false;
    }
    
    try {
        $sql = "INSERT INTO school_years (name, start_date, end_date, is_active) VALUES (?, ?, ?, 0)";
        return dbInsert($sql, [$name, $startDate, $endDate]);
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to create school year: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Set a school year as active (deactivates others)
 * 
 * @param int $schoolYearId School year ID to activate
 * @return bool Success status
 * 
 * Requirements: 1.3
 */
function setActiveSchoolYear(int $schoolYearId): bool
{
    // Verify school year exists
    $checkSql = "SELECT id FROM school_years WHERE id = ?";
    $schoolYear = dbFetchOne($checkSql, [$schoolYearId]);
    
    if (!$schoolYear) {
        return false;
    }
    
    try {
        // Deactivate all school years first
        $deactivateSql = "UPDATE school_years SET is_active = 0";
        dbExecute($deactivateSql);
        
        // Activate the specified school year
        $activateSql = "UPDATE school_years SET is_active = 1 WHERE id = ?";
        dbExecute($activateSql, [$schoolYearId]);
        
        return true;
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('Failed to set active school year: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Get a school year by ID
 * 
 * @param int $schoolYearId School year ID
 * @return array|null School year record or null if not found
 */
function getSchoolYearById(int $schoolYearId): ?array
{
    $sql = "SELECT id, name, is_active, start_date, end_date, created_at, updated_at 
            FROM school_years 
            WHERE id = ?";
    
    return dbFetchOne($sql, [$schoolYearId]);
}
