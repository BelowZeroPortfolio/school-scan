<?php
/**
 * CSV Export Functions
 * Export attendance reports to CSV format
 */

require_once __DIR__ . '/logger.php';

/**
 * Export report data to CSV
 * 
 * @param array $data Report data
 * @param string $filename Output filename (without extension)
 * @return string|false File path on success, false on failure
 */
function exportToCsv($data, $filename = 'attendance_report') {
    try {
        // Ensure storage directory exists
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
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
        
        // Write header row
        $headers = [
            'Date',
            'Student Number',
            'First Name',
            'Last Name',
            'Class',
            'Section',
            'Check-in Time',
            'Status',
            'Recorded By'
        ];
        fputcsv($file, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            $csvRow = [
                $row['attendance_date'] ?? '',
                $row['student_number'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['class'] ?? '',
                $row['section'] ?? '',
                $row['check_in_time'] ?? '',
                ucfirst($row['status'] ?? ''),
                $row['recorded_by'] ?? ''
            ];
            fputcsv($file, $csvRow);
        }
        
        fclose($file);
        
        logInfo('CSV export created', [
            'filename' => basename($filepath),
            'record_count' => count($data)
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
 * @return string|false File path on success, false on failure
 */
function exportStudentSummaryCsv($data, $filename = 'student_summary') {
    try {
        // Ensure storage directory exists
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
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
            $csvRow = [
                $row['student_number'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['class'] ?? '',
                $row['section'] ?? '',
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
            'record_count' => count($data)
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
