<?php
/**
 * Excel Export Functions
 * Export attendance reports to Excel format using PhpSpreadsheet
 * 
 * Requirements: 8.2, 8.3
 */

// require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/reports.php';

// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Style\Fill;
// use PhpOffice\PhpSpreadsheet\Style\Alignment;
// use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export report data to Excel
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
function exportToExcel($data, $stats = [], $filename = 'attendance_report', $options = []) {
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
        $filepath = $exportDir . '/' . $filename . '_' . $timestamp . '.xlsx';
        
        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Report');
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(20);
        
        $row = 1;
        
        // Title
        $sheet->setCellValue('A' . $row, 'Attendance Report');
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;
        
        // Report info
        $sheet->setCellValue('A' . $row, 'Generated:');
        $sheet->setCellValue('B' . $row, date('Y-m-d H:i:s'));
        $row++;
        
        // School year info (Requirements: 8.3)
        $schoolYearName = $options['school_year_name'] ?? ($stats['school_year_name'] ?? null);
        if (!$schoolYearName && isset($options['school_year_id'])) {
            $schoolYearName = getReportSchoolYearName($options['school_year_id']);
        }
        if ($schoolYearName) {
            $sheet->setCellValue('A' . $row, 'School Year:');
            $sheet->setCellValue('B' . $row, $schoolYearName);
            $row++;
        }
        
        if (!empty($stats['date_range']['start']) && !empty($stats['date_range']['end'])) {
            $sheet->setCellValue('A' . $row, 'Period:');
            $sheet->setCellValue('B' . $row, $stats['date_range']['start'] . ' to ' . $stats['date_range']['end']);
            $row++;
        }
        
        $row++;
        
        // Statistics summary
        if (!empty($stats)) {
            $sheet->setCellValue('A' . $row, 'Summary Statistics');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Total Records:');
            $sheet->setCellValue('B' . $row, $stats['total_records'] ?? 0);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Present:');
            $sheet->setCellValue('B' . $row, $stats['present'] ?? 0);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Late:');
            $sheet->setCellValue('B' . $row, $stats['late'] ?? 0);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Absent:');
            $sheet->setCellValue('B' . $row, $stats['absent'] ?? 0);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Attendance Percentage:');
            $sheet->setCellValue('B' . $row, ($stats['attendance_percentage'] ?? 0) . '%');
            $row++;
            
            $row++;
        }
        
        // Attendance records table
        if (!empty($data)) {
            $sheet->setCellValue('A' . $row, 'Attendance Records');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;
            
            // Table header
            $headerRow = $row;
            $headers = ['Student ID', 'Name', 'Class', 'Arrival Date', 'Arrival Time', 'Dismissal Date', 'Dismissal Time', 'Status', 'Recorded By'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, $header);
                $col++;
            }
            
            // Style header row
            $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '8B5CF6'] // Purple
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            
            $row++;
            
            // Table data
            foreach ($data as $record) {
                // Use class-based data if available, fall back to legacy
                $classValue = $record['class_grade'] ?? $record['class'] ?? '';
                $sectionValue = $record['class_section'] ?? $record['section'] ?? '';
                $classDisplay = $classValue . ($sectionValue ? ' - ' . $sectionValue : '');
                
                // Format arrival time
                $arrivalTime = '';
                if (!empty($record['check_in_time'])) {
                    $arrivalTime = date('h:i A', strtotime($record['check_in_time']));
                }
                
                // Format dismissal date and time from check_out_time
                $dismissalDate = '';
                $dismissalTime = '';
                if (!empty($record['check_out_time'])) {
                    $dismissalDate = date('M d, Y', strtotime($record['check_out_time']));
                    $dismissalTime = date('h:i A', strtotime($record['check_out_time']));
                }
                
                $sheet->setCellValue('A' . $row, $record['student_number'] ?? '');
                $sheet->setCellValue('B' . $row, ($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''));
                $sheet->setCellValue('C' . $row, $classDisplay);
                $sheet->setCellValue('D' . $row, $record['attendance_date'] ?? '');
                $sheet->setCellValue('E' . $row, $arrivalTime);
                $sheet->setCellValue('F' . $row, $dismissalDate);
                $sheet->setCellValue('G' . $row, $dismissalTime);
                $sheet->setCellValue('H' . $row, ucfirst($record['status'] ?? ''));
                $sheet->setCellValue('I' . $row, $record['recorded_by'] ?? '');
                
                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F5F5F5']
                        ]
                    ]);
                }
                
                $row++;
            }
            
            // Add borders to table
            $sheet->getStyle('A' . $headerRow . ':I' . ($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
        }
        
        // Write to file
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        logInfo('Excel export created', [
            'filename' => basename($filepath),
            'record_count' => count($data),
            'school_year' => $schoolYearName ?? null
        ]);
        
        return $filepath;
    } catch (Exception $e) {
        logError('Excel export failed: ' . $e->getMessage(), [
            'filename' => $filename
        ]);
        return false;
    }
}

/**
 * Export student summary to Excel
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
function exportStudentSummaryExcel($data, $filename = 'student_summary', $options = []) {
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
        $filepath = $exportDir . '/' . $filename . '_' . $timestamp . '.xlsx';
        
        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Student Summary');
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(10);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(15);
        
        $row = 1;
        
        // Title
        $sheet->setCellValue('A' . $row, 'Student Attendance Summary');
        $sheet->mergeCells('A' . $row . ':J' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;
        
        // Report info
        $sheet->setCellValue('A' . $row, 'Generated:');
        $sheet->setCellValue('B' . $row, date('Y-m-d H:i:s'));
        $row++;
        
        // School year info (Requirements: 8.3)
        $schoolYearName = $options['school_year_name'] ?? null;
        if (!$schoolYearName && isset($options['school_year_id'])) {
            $schoolYearName = getReportSchoolYearName($options['school_year_id']);
        }
        if ($schoolYearName) {
            $sheet->setCellValue('A' . $row, 'School Year:');
            $sheet->setCellValue('B' . $row, $schoolYearName);
            $row++;
        }
        
        $row++;
        
        // Table header
        $headerRow = $row;
        $headers = ['Student Number', 'First Name', 'Last Name', 'Class', 'Section', 'Present Days', 'Late Days', 'Absent Days', 'Total Records', 'Attendance %'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        
        // Style header row
        $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '8B5CF6'] // Purple
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        $row++;
        
        // Table data
        foreach ($data as $record) {
            // Use class-based data if available, fall back to legacy
            $classValue = $record['class_grade'] ?? $record['class'] ?? '';
            $sectionValue = $record['class_section'] ?? $record['section'] ?? '';
            
            $sheet->setCellValue('A' . $row, $record['student_number'] ?? '');
            $sheet->setCellValue('B' . $row, $record['first_name'] ?? '');
            $sheet->setCellValue('C' . $row, $record['last_name'] ?? '');
            $sheet->setCellValue('D' . $row, $classValue);
            $sheet->setCellValue('E' . $row, $sectionValue);
            $sheet->setCellValue('F' . $row, $record['present_count'] ?? 0);
            $sheet->setCellValue('G' . $row, $record['late_count'] ?? 0);
            $sheet->setCellValue('H' . $row, $record['absent_count'] ?? 0);
            $sheet->setCellValue('I' . $row, $record['total_records'] ?? 0);
            $sheet->setCellValue('J' . $row, ($record['attendance_percentage'] ?? 0) . '%');
            
            // Alternate row colors
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F5F5F5']
                    ]
                ]);
            }
            
            $row++;
        }
        
        // Add borders to table
        $sheet->getStyle('A' . $headerRow . ':J' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
        
        // Write to file
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        logInfo('Student summary Excel export created', [
            'filename' => basename($filepath),
            'record_count' => count($data),
            'school_year' => $schoolYearName ?? null
        ]);
        
        return $filepath;
    } catch (Exception $e) {
        logError('Student summary Excel export failed: ' . $e->getMessage(), [
            'filename' => $filename
        ]);
        return false;
    }
}

/**
 * Download Excel file to browser
 * 
 * @param string $filepath Full path to Excel file
 * @param string $downloadName Filename for download
 * @return void
 */
function downloadExcel($filepath, $downloadName = null) {
    if (!file_exists($filepath)) {
        logError('Excel file not found for download', ['filepath' => $filepath]);
        die('File not found');
    }
    
    if ($downloadName === null) {
        $downloadName = basename($filepath);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($filepath);
    
    logInfo('Excel file downloaded', ['filename' => $downloadName]);
    
    exit;
}
