<?php
/**
 * CSV Export Functions
 * Export attendance reports to CSV format
 * 
 * Requirements: 8.2, 8.3
 */

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/reports.php';

/**
 * Export report data to CSV
 * 
 * @param array $data Report data
 * @param string $filename Output filename (without extension)
 * @param array $options Export options
 *   - school_year_id: School year ID for filename
 *   - school_year_name: School year name for header
 *   - include_school_year: Whether to include school year in filename (default: true)
 * @return string|false File path on success, false on failure
 * 
 * Requirements: 8.2, 8.3
 */
function exportToCsv($data, $filename = 'attendance_report', $options = []) {
    try {
        // Ensure storage directory exists
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Build filename with school year if enabled (Requirements: 8.3)
        $includeSchoolYear = $options['include_school_year'] ?? true;
        if ($includeSchoolYear) {
            $schoolYearId = $options['school_year_id'] ?? null;
            $filename = buildExportFilename($filename, $schoolYearId);
        }
        
        // Generate unique filename
        $timestamp = date('Y-m-d_His');
        $filepath = $exportDir . '/' . $filename . '_' . $timestamp . '.csv';
        
        // Open file for writing
        $file = fopen($filepath, 'w');
        if (!$file) {
            throw new Exception('Failed to create CSV file');
        }
        
        // Write UTF-8 BOM for Excel compatibility
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write school year header if available (Requirements: 8.3)
        $schoolYearName = $options['school_year_name'] ?? null;
        if (!$schoolYearName && isset($options['school_year_id'])) {
            $schoolYearName = getReportSchoolYearName($options['school_year_id']);
        }
        if ($schoolYearName) {
            fputcsv($file, ['School Year: ' . $schoolYearName]);
            fputcsv($file, []); // Empty row for spacing
        }
        
        // Write header row
        $headers = [
            'Student ID',
            'Name',
            'Class',
            'Arrival Date',
            'Arrival Time',
            'Dismissal Date',
            'Dismissal Time',
            'Status',
            'Recorded By'
        ];
        fputcsv($file, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            // Use class-based data if available, fall back to legacy
            $classValue = $row['class_grade'] ?? $row['class'] ?? '';
            $sectionValue = $row['class_section'] ?? $row['section'] ?? '';
            $classDisplay = $classValue . ($sectionValue ? ' - ' . $sectionValue : '');
            
            // Format arrival time
            $arrivalTime = '';
            if (!empty($row['check_in_time'])) {
                $arrivalTime = date('h:i A', strtotime($row['check_in_time']));
            }
            
            // Format dismissal date and time from check_out_time
            $dismissalDate = '';
            $dismissalTime = '';
            if (!empty($row['check_out_time'])) {
                $dismissalDate = date('M d, Y', strtotime($row['check_out_time']));
                $dismissalTime = date('h:i A', strtotime($row['check_out_time']));
            }
            
            $csvRow = [
                $row['student_number'] ?? '',
                ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''),
                $classDisplay,
                $row['attendance_date'] ?? '',
                $arrivalTime,
                $dismissalDate,
                $dismissalTime,
                ucfirst($row['status'] ?? ''),
                $row['recorded_by'] ?? ''
            ];
            fputcsv($file, $csvRow);
        }
        
        fclose($file);
        
        logInfo('CSV export created', [
            'filename' => basename($filepath),
            'record_count' => count($data),
            'school_year' => $schoolYearName
        ]);
        
        return $filepath;
    } catch (Exception $e) {
        logError('CSV export failed: ' . $e->getMessage(), [
            'filename' => $filename
        ]);
        return false;
    }
}

/**
 * Export student summary to CSV
 * 
 * @param array $data Student summary data
 * @param string $filename Output filename (without extension)
 * @param array $options Export options
 *   - school_year_id: School year ID for filename
 *   - school_year_name: School year name for header
 *   - include_school_year: Whether to include school year in filename (default: true)
 * @return string|false File path on success, false on failure
 * 
 * Requirements: 8.2, 8.3
 */
function exportStudentSummaryCsv($data, $filename = 'student_summary', $options = []) {
    try {
        // Ensure storage directory exists
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Build filename with school year if enabled (Requirements: 8.3)
        $includeSchoolYear = $options['include_school_year'] ?? true;
        if ($includeSchoolYear) {
            $schoolYearId = $options['school_year_id'] ?? null;
            $filename = buildExportFilename($filename, $schoolYearId);
        }
        
        // Generate unique filename
        $timestamp = date('Y-m-d_His');
        $filepath = $exportDir . '/' . $filename . '_' . $timestamp . '.csv';
        
        // Open file for writing
        $file = fopen($filepath, 'w');
        if (!$file) {
            throw new Exception('Failed to create CSV file');
        }
        
        // Write UTF-8 BOM for Excel compatibility
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write school year header if available (Requirements: 8.3)
        $schoolYearName = $options['school_year_name'] ?? null;
        if (!$schoolYearName && isset($options['school_year_id'])) {
            $schoolYearName = getReportSchoolYearName($options['school_year_id']);
        }
        if ($schoolYearName) {
            fputcsv($file, ['School Year: ' . $schoolYearName]);
            fputcsv($file, []); // Empty row for spacing
        }
        
        // Write header row
        $headers = [
            'Student Number',
            'First Name',
            'Last Name',
            'Class',
            'Section',
            'Present Days',
            'Late Days',
            'Absent Days',
            'Total Records',
            'Attendance %'
        ];
        fputcsv($file, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            // Use class-based data if available, fall back to legacy
            $classValue = $row['class_grade'] ?? $row['class'] ?? '';
            $sectionValue = $row['class_section'] ?? $row['section'] ?? '';
            
            $csvRow = [
                $row['student_number'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $classValue,
                $sectionValue,
                $row['present_count'] ?? 0,
                $row['late_count'] ?? 0,
                $row['absent_count'] ?? 0,
                $row['total_records'] ?? 0,
                ($row['attendance_percentage'] ?? 0) . '%'
            ];
            fputcsv($file, $csvRow);
        }
        
        fclose($file);
        
        logInfo('Student summary CSV export created', [
            'filename' => basename($filepath),
            'record_count' => count($data),
            'school_year' => $schoolYearName
        ]);
        
        return $filepath;
    } catch (Exception $e) {
        logError('Student summary CSV export failed: ' . $e->getMessage(), [
            'filename' => $filename
        ]);
        return false;
    }
}

/**
 * Download CSV file to browser
 * 
 * @param string $filepath Full path to CSV file
 * @param string $downloadName Filename for download
 * @return void
 */
function downloadCsv($filepath, $downloadName = null) {
    if (!file_exists($filepath)) {
        logError('CSV file not found for download', ['filepath' => $filepath]);
        die('File not found');
    }
    
    if ($downloadName === null) {
        $downloadName = basename($filepath);
    }
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($filepath);
    
    logInfo('CSV file downloaded', ['filename' => $downloadName]);
    
    exit;
}
