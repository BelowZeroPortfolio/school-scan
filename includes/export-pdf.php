<?php
/**
 * PDF Export Functions
 * Export attendance reports to PDF format using TCPDF
 * 
 * Requirements: 8.2, 8.3
 */

// require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/reports.php';

// use TCPDF;

/**
 * Export report data to PDF
 * 
 * @param array $data Report data
 * @param array $stats Report statistics
 * @param string $filename Output filename (without extension)
 * @param array $options Export options
 *   - school_year_id: School year ID for filename
 *   - school_year_name: School year name for header
 *   - include_school_year: Whether to include school year in filename (default: true)
 * @return string|false File path on success, false on failure
 * 
 * Requirements: 8.2, 8.3
 */
function exportToPdf($data, $stats = [], $filename = 'attendance_report', $options = []) {
    try {
        // Ensure storage directory exists
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Build filename with school year if enabled (Requirements: 8.3)
        $includeSchoolYear = $options['include_school_year'] ?? true;
        if ($includeSchoolYear) {
            $schoolYearId = $options['school_year_id'] ?? ($stats['school_year_id'] ?? null);
            $filename = buildExportFilename($filename, $schoolYearId);
        }
        
        // Generate unique filename
        $timestamp = date('Y-m-d_His');
        $filepath = $exportDir . '/' . $filename . '_' . $timestamp . '.pdf';
        
        // Create new PDF document
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Attendance System');
        $pdf->SetAuthor('Attendance System');
        $pdf->SetTitle('Attendance Report');
        $pdf->SetSubject('Attendance Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Attendance Report', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Report info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1);
        
        // School year info (Requirements: 8.3)
        $schoolYearName = $options['school_year_name'] ?? ($stats['school_year_name'] ?? null);
        if (!$schoolYearName && isset($options['school_year_id'])) {
            $schoolYearName = getReportSchoolYearName($options['school_year_id']);
        }
        if ($schoolYearName) {
            $pdf->Cell(0, 6, 'School Year: ' . $schoolYearName, 0, 1);
        }
        
        if (!empty($stats['date_range']['start']) && !empty($stats['date_range']['end'])) {
            $pdf->Cell(0, 6, 'Period: ' . $stats['date_range']['start'] . ' to ' . $stats['date_range']['end'], 0, 1);
        }
        
        $pdf->Ln(5);
        
        // Statistics summary
        if (!empty($stats)) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Summary Statistics', 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $pdf->Cell(60, 6, 'Total Records:', 0, 0);
            $pdf->Cell(0, 6, $stats['total_records'] ?? 0, 0, 1);
            
            $pdf->Cell(60, 6, 'Present:', 0, 0);
            $pdf->Cell(0, 6, $stats['present'] ?? 0, 0, 1);
            
            $pdf->Cell(60, 6, 'Late:', 0, 0);
            $pdf->Cell(0, 6, $stats['late'] ?? 0, 0, 1);
            
            $pdf->Cell(60, 6, 'Absent:', 0, 0);
            $pdf->Cell(0, 6, $stats['absent'] ?? 0, 0, 1);
            
            $pdf->Cell(60, 6, 'Attendance Percentage:', 0, 0);
            $pdf->Cell(0, 6, ($stats['attendance_percentage'] ?? 0) . '%', 0, 1);
            
            $pdf->Ln(5);
        }
        
        // Attendance records table
        if (!empty($data)) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Attendance Records', 0, 1);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(139, 92, 246); // Purple
            $pdf->SetTextColor(255, 255, 255);
            
            $pdf->Cell(25, 7, 'Date', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Student ID', 1, 0, 'C', true);
            $pdf->Cell(35, 7, 'Name', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Class', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Time', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Status', 1, 1, 'C', true);
            
            // Table data
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(245, 245, 245);
            
            $fill = false;
            foreach ($data as $row) {
                // Check if we need a new page
                if ($pdf->GetY() > 260) {
                    $pdf->AddPage();
                    
                    // Repeat header on new page
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetFillColor(139, 92, 246);
                    $pdf->SetTextColor(255, 255, 255);
                    
                    $pdf->Cell(25, 7, 'Date', 1, 0, 'C', true);
                    $pdf->Cell(25, 7, 'Student ID', 1, 0, 'C', true);
                    $pdf->Cell(35, 7, 'Name', 1, 0, 'C', true);
                    $pdf->Cell(20, 7, 'Class', 1, 0, 'C', true);
                    $pdf->Cell(30, 7, 'Time', 1, 0, 'C', true);
                    $pdf->Cell(20, 7, 'Status', 1, 1, 'C', true);
                    
                    $pdf->SetFont('helvetica', '', 8);
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                $name = ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '');
                $classValue = $row['class_grade'] ?? $row['class'] ?? '';
                $sectionValue = $row['class_section'] ?? $row['section'] ?? '';
                $class = $classValue . ($sectionValue ? ' ' . $sectionValue : '');
                $time = date('H:i', strtotime($row['check_in_time'] ?? ''));
                
                $pdf->Cell(25, 6, $row['attendance_date'] ?? '', 1, 0, 'C', $fill);
                $pdf->Cell(25, 6, $row['student_number'] ?? '', 1, 0, 'C', $fill);
                $pdf->Cell(35, 6, substr($name, 0, 20), 1, 0, 'L', $fill);
                $pdf->Cell(20, 6, $class, 1, 0, 'C', $fill);
                $pdf->Cell(30, 6, $time, 1, 0, 'C', $fill);
                $pdf->Cell(20, 6, ucfirst($row['status'] ?? ''), 1, 1, 'C', $fill);
                
                $fill = !$fill;
            }
        }
        
        // Output PDF to file
        $pdf->Output($filepath, 'F');
        
        logInfo('PDF export created', [
            'filename' => basename($filepath),
            'record_count' => count($data),
            'school_year' => $schoolYearName
        ]);
        
        return $filepath;
    } catch (Exception $e) {
        logError('PDF export failed: ' . $e->getMessage(), [
            'filename' => $filename
        ]);
        return false;
    }
}

/**
 * Export student summary to PDF
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
function exportStudentSummaryPdf($data, $filename = 'student_summary', $options = []) {
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
        $filepath = $exportDir . '/' . $filename . '_' . $timestamp . '.pdf';
        
        // Create new PDF document
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Landscape
        
        // Set document information
        $pdf->SetCreator('Attendance System');
        $pdf->SetAuthor('Attendance System');
        $pdf->SetTitle('Student Attendance Summary');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Student Attendance Summary', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Report info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1);
        
        // School year info (Requirements: 8.3)
        $schoolYearName = $options['school_year_name'] ?? null;
        if (!$schoolYearName && isset($options['school_year_id'])) {
            $schoolYearName = getReportSchoolYearName($options['school_year_id']);
        }
        if ($schoolYearName) {
            $pdf->Cell(0, 6, 'School Year: ' . $schoolYearName, 0, 1);
        }
        
        $pdf->Ln(5);
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(139, 92, 246); // Purple
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(30, 7, 'Student ID', 1, 0, 'C', true);
        $pdf->Cell(50, 7, 'Name', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Class', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Present', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Late', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Absent', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Total', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Attendance %', 1, 1, 'C', true);
        
        // Table data
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(245, 245, 245);
        
        $fill = false;
        foreach ($data as $row) {
            // Check if we need a new page
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
                
                // Repeat header
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(139, 92, 246);
                $pdf->SetTextColor(255, 255, 255);
                
                $pdf->Cell(30, 7, 'Student ID', 1, 0, 'C', true);
                $pdf->Cell(50, 7, 'Name', 1, 0, 'C', true);
                $pdf->Cell(25, 7, 'Class', 1, 0, 'C', true);
                $pdf->Cell(25, 7, 'Present', 1, 0, 'C', true);
                $pdf->Cell(25, 7, 'Late', 1, 0, 'C', true);
                $pdf->Cell(25, 7, 'Absent', 1, 0, 'C', true);
                $pdf->Cell(25, 7, 'Total', 1, 0, 'C', true);
                $pdf->Cell(30, 7, 'Attendance %', 1, 1, 'C', true);
                
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetTextColor(0, 0, 0);
            }
            
            $name = ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '');
            // Use class-based data if available, fall back to legacy
            $classValue = $row['class_grade'] ?? $row['class'] ?? '';
            $sectionValue = $row['class_section'] ?? $row['section'] ?? '';
            $class = $classValue . ($sectionValue ? ' ' . $sectionValue : '');
            
            $pdf->Cell(30, 6, $row['student_number'] ?? '', 1, 0, 'C', $fill);
            $pdf->Cell(50, 6, substr($name, 0, 30), 1, 0, 'L', $fill);
            $pdf->Cell(25, 6, $class, 1, 0, 'C', $fill);
            $pdf->Cell(25, 6, $row['present_count'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell(25, 6, $row['late_count'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell(25, 6, $row['absent_count'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell(25, 6, $row['total_records'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell(30, 6, ($row['attendance_percentage'] ?? 0) . '%', 1, 1, 'C', $fill);
            
            $fill = !$fill;
        }
        
        // Output PDF to file
        $pdf->Output($filepath, 'F');
        
        logInfo('Student summary PDF export created', [
            'filename' => basename($filepath),
            'record_count' => count($data),
            'school_year' => $schoolYearName ?? null
        ]);
        
        return $filepath;
    } catch (Exception $e) {
        logError('Student summary PDF export failed: ' . $e->getMessage(), [
            'filename' => $filename
        ]);
        return false;
    }
}

/**
 * Download PDF file to browser
 * 
 * @param string $filepath Full path to PDF file
 * @param string $downloadName Filename for download
 * @return void
 */
function downloadPdf($filepath, $downloadName = null) {
    if (!file_exists($filepath)) {
        logError('PDF file not found for download', ['filepath' => $filepath]);
        die('File not found');
    }
    
    if ($downloadName === null) {
        $downloadName = basename($filepath);
    }
    
    // Set headers for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($filepath);
    
    logInfo('PDF file downloaded', ['filename' => $downloadName]);
    
    exit;
}
