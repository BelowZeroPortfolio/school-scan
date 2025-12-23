<?php
/**
 * PDF Export Functions
 * Uses TCPDF library for PDF generation
 */

// Only load TCPDF if vendor autoload exists
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

/**
 * Export attendance data to PDF
 * 
 * @param array $data Attendance records
 * @param array $stats Statistics array
 * @param string $filename Base filename without extension
 * @return string|false Path to generated file or false on failure
 */
function exportToPdf($data, $stats = [], $filename = 'attendance_report') {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        error_log('PDF Export Error: TCPDF library not installed. Run: composer install');
        return false;
    }
    
    try {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Attendance System');
        $pdf->SetAuthor('School Administration');
        $pdf->SetTitle('Attendance Report');
        $pdf->SetSubject('Attendance Records');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        
        // Add a page
        $pdf->AddPage('L'); // Landscape for better table fit
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Attendance Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Statistics if provided
        if (!empty($stats)) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, 'Summary Statistics', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            $statsText = sprintf(
                'Total Records: %d | Present: %d | Late: %d | Absent: %d | Attendance Rate: %.1f%%',
                $stats['total_records'] ?? 0,
                $stats['present'] ?? 0,
                $stats['late'] ?? 0,
                $stats['absent'] ?? 0,
                $stats['attendance_percentage'] ?? 0
            );
            $pdf->Cell(0, 6, $statsText, 0, 1, 'L');
            $pdf->Ln(5);
        }
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(139, 92, 246); // Violet
        $pdf->SetTextColor(255, 255, 255);
        
        // Column widths (total ~277 for landscape A4)
        $colWidths = [25, 45, 35, 30, 25, 25, 25, 25, 20, 30];
        $headers = ['Student ID', 'Name', 'Class', 'Arrival Date', 'Arrival Time', 'Dismissal Date', 'Dismissal Time', 'Status', 'Recorded By'];
        
        // Adjust widths based on actual columns
        $colWidths = [28, 50, 35, 28, 25, 28, 25, 22, 36];
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Table data
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        
        foreach ($data as $row) {
            if ($fill) {
                $pdf->SetFillColor(245, 245, 245);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            // Format the data
            $studentId = $row['student_number'] ?? $row['student_id'] ?? '';
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $class = ($row['class'] ?? '') . ($row['section'] ? ' - ' . $row['section'] : '');
            
            // Parse arrival date/time
            $arrivalDate = '';
            $arrivalTime = '';
            if (!empty($row['check_in_time'])) {
                if (strpos($row['check_in_time'], ' ') !== false) {
                    list($arrivalDate, $arrivalTime) = explode(' ', $row['check_in_time']);
                } else {
                    $arrivalTime = $row['check_in_time'];
                    $arrivalDate = $row['attendance_date'] ?? '';
                }
            }
            
            // Parse dismissal date/time
            $dismissalDate = '';
            $dismissalTime = '';
            if (!empty($row['check_out_time'])) {
                if (strpos($row['check_out_time'], ' ') !== false) {
                    list($dismissalDate, $dismissalTime) = explode(' ', $row['check_out_time']);
                } else {
                    $dismissalTime = $row['check_out_time'];
                    $dismissalDate = $row['attendance_date'] ?? '';
                }
            }
            
            $status = ucfirst($row['status'] ?? '');
            $recordedBy = $row['recorded_by'] ?? $row['recorded_by_name'] ?? 'System';
            
            $pdf->Cell($colWidths[0], 6, $studentId, 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[1], 6, $name, 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[2], 6, $class, 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[3], 6, $arrivalDate, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[4], 6, $arrivalTime, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[5], 6, $dismissalDate, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[6], 6, $dismissalTime, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[7], 6, $status, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[8], 6, $recordedBy, 1, 0, 'L', $fill);
            $pdf->Ln();
            
            $fill = !$fill;
        }
        
        // Save to file
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filepath = $exportDir . '/' . $filename . '_' . date('Y-m-d_His') . '.pdf';
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    } catch (Exception $e) {
        error_log('PDF Export Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Export student summary report to PDF
 * 
 * @param array $data Student summary data
 * @param array $filters Applied filters
 * @param string $filename Base filename
 * @return string|false Path to generated file or false on failure
 */
function exportStudentSummaryPdf($data, $filters = [], $filename = 'student_summary') {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        error_log('PDF Export Error: TCPDF library not installed. Run: composer install');
        return false;
    }
    
    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Attendance System');
        $pdf->SetAuthor('School Administration');
        $pdf->SetTitle('Student Attendance Summary');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        
        $pdf->AddPage('L');
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Student Attendance Summary', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        
        // Date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $pdf->Cell(0, 5, 'Period: ' . $filters['start_date'] . ' to ' . $filters['end_date'], 0, 1, 'C');
        }
        $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(139, 92, 246);
        $pdf->SetTextColor(255, 255, 255);
        
        $colWidths = [30, 55, 40, 25, 25, 25, 25, 30, 30];
        $headers = ['Student ID', 'Name', 'Class', 'Present', 'Late', 'Absent', 'Total', 'Rate (%)', 'Status'];
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Data rows
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        
        foreach ($data as $row) {
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
            
            $pdf->Cell($colWidths[0], 6, $row['student_id'] ?? '', 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[1], 6, $row['student_name'] ?? '', 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[2], 6, $row['class'] ?? '', 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[3], 6, $row['present_count'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[4], 6, $row['late_count'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[5], 6, $row['absent_count'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[6], 6, $row['total_days'] ?? 0, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[7], 6, number_format($row['attendance_rate'] ?? 0, 1), 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[8], 6, $row['status'] ?? '', 1, 0, 'C', $fill);
            $pdf->Ln();
            
            $fill = !$fill;
        }
        
        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filepath = $exportDir . '/' . $filename . '_' . date('Y-m-d_His') . '.pdf';
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    } catch (Exception $e) {
        error_log('PDF Export Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Download PDF file
 * 
 * @param string $filepath Path to PDF file
 * @param string $downloadName Filename for download
 */
function downloadPdf($filepath, $downloadName = 'report.pdf') {
    if (!file_exists($filepath)) {
        return false;
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    readfile($filepath);
    
    // Clean up
    unlink($filepath);
    exit;
}
