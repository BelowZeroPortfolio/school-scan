<?php
/**
 * Barcode and QR Code Generation Functions
 * Generates Code 128 barcodes and QR codes for student ID cards
 */

if (!isset($GLOBALS['config'])) {
    require_once __DIR__ . '/../config/config.php';
}

// Load Composer autoloader for QR code library
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Generate QR Code SVG
 */
function generateQRCode($data, $filename) {
    // Ensure storage directory exists
    $storageDir = __DIR__ . '/../storage/barcodes';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    
    $filename = preg_replace('/\.(png|svg)$/', '', $filename) . '.svg';
    $filepath = $storageDir . '/' . $filename;
    
    // Check if chillerlan/php-qrcode is available
    if (class_exists('chillerlan\QRCode\QRCode')) {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 8,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);
        
        $qrcode = new QRCode($options);
        $svg = $qrcode->render($data);
        
        file_put_contents($filepath, $svg);
    } else {
        // Fallback to built-in implementation
        $svg = generateQRCodeFallback($data);
        file_put_contents($filepath, $svg);
    }
    
    return 'storage/barcodes/' . $filename;
}

/**
 * Fallback QR code generation (basic implementation)
 */
function generateQRCodeFallback($data) {
    $qr = new QRCodeGenerator($data);
    return $qr->toSVG();
}

/**
 * Simple QR Code Generator class (fallback when library not available)
 */
class QRCodeGenerator {
    private $data;
    private $size = 21; // Version 1 QR code
    private $matrix;
    
    public function __construct($data) {
        $this->data = $data;
        $this->matrix = array_fill(0, $this->size, array_fill(0, $this->size, 0));
        $this->generate();
    }
    
    private function generate() {
        // Add finder patterns
        $this->addFinderPattern(0, 0);
        $this->addFinderPattern($this->size - 7, 0);
        $this->addFinderPattern(0, $this->size - 7);
        
        // Add separators
        $this->addSeparators();
        
        // Add timing patterns
        $this->addTimingPatterns();
        
        // Add dark module
        $this->matrix[8][$this->size - 8] = 1;
        
        // Encode data
        $this->encodeData();
    }
    
    private function addFinderPattern($row, $col) {
        $pattern = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1]
        ];
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                if ($row + $r < $this->size && $col + $c < $this->size) {
                    $this->matrix[$row + $r][$col + $c] = $pattern[$r][$c];
                }
            }
        }
    }
    
    private function addSeparators() {
        // Top-left
        for ($i = 0; $i < 8; $i++) {
            if ($i < $this->size) {
                $this->matrix[7][$i] = 0;
                $this->matrix[$i][7] = 0;
            }
        }
        // Top-right
        for ($i = 0; $i < 8; $i++) {
            if ($this->size - 8 + $i < $this->size) {
                $this->matrix[7][$this->size - 8 + $i] = 0;
            }
            $this->matrix[$i][$this->size - 8] = 0;
        }
        // Bottom-left
        for ($i = 0; $i < 8; $i++) {
            $this->matrix[$this->size - 8][$i] = 0;
            if ($this->size - 8 + $i < $this->size) {
                $this->matrix[$this->size - 8 + $i][7] = 0;
            }
        }
    }
    
    private function addTimingPatterns() {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $val = ($i % 2 == 0) ? 1 : 0;
            $this->matrix[6][$i] = $val;
            $this->matrix[$i][6] = $val;
        }
    }
    
    private function encodeData() {
        $binary = '';
        for ($i = 0; $i < strlen($this->data); $i++) {
            $binary .= str_pad(decbin(ord($this->data[$i])), 8, '0', STR_PAD_LEFT);
        }
        
        $pos = 0;
        $upward = true;
        
        for ($col = $this->size - 1; $col >= 0; $col -= 2) {
            if ($col == 6) $col = 5;
            
            $rows = $upward ? range($this->size - 1, 0, -1) : range(0, $this->size - 1);
            
            foreach ($rows as $row) {
                for ($c = 0; $c < 2; $c++) {
                    $currentCol = $col - $c;
                    if ($currentCol < 0) continue;
                    
                    if (!$this->isReserved($row, $currentCol)) {
                        if ($pos < strlen($binary)) {
                            $this->matrix[$row][$currentCol] = ($binary[$pos] == '1') ? 1 : 0;
                            $pos++;
                        }
                    }
                }
            }
            $upward = !$upward;
        }
    }
    
    private function isReserved($row, $col) {
        // Finder patterns + separators
        if ($row < 9 && $col < 9) return true;
        if ($row < 9 && $col >= $this->size - 8) return true;
        if ($row >= $this->size - 8 && $col < 9) return true;
        // Timing patterns
        if ($row == 6 || $col == 6) return true;
        return false;
    }
    
    public function toSVG() {
        $cellSize = 8;
        $padding = 20;
        $width = $this->size * $cellSize + $padding * 2;
        
        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $width . '" viewBox="0 0 ' . $width . ' ' . $width . '">' . "\n";
        $svg .= '<rect width="100%" height="100%" fill="white"/>' . "\n";
        
        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->matrix[$row][$col] == 1) {
                    $x = $padding + $col * $cellSize;
                    $y = $padding + $row * $cellSize;
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cellSize . '" height="' . $cellSize . '" fill="black"/>' . "\n";
                }
            }
        }
        
        $svg .= '</svg>';
        return $svg;
    }
}

/**
 * Generate QR code for student
 */
function generateStudentQRCode($studentId) {
    $filename = 'qr_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentId) . '.svg';
    return generateQRCode($studentId, $filename);
}

/**
 * Code 128 encoding tables
 */
function getCode128Patterns() {
    // Code 128B patterns (supports 0-9, A-Z, a-z, and special chars)
    return [
        ' ' => '11011001100', '!' => '11001101100', '"' => '11001100110', '#' => '10010011000',
        '$' => '10010001100', '%' => '10001001100', '&' => '10011001000', "'" => '10011000100',
        '(' => '10001100100', ')' => '11001001000', '*' => '11001000100', '+' => '11000100100',
        ',' => '10110011100', '-' => '10011011100', '.' => '10011001110', '/' => '10111001100',
        '0' => '10011101100', '1' => '10011100110', '2' => '11001110010', '3' => '11001011100',
        '4' => '11001001110', '5' => '11011100100', '6' => '11001110100', '7' => '11101101110',
        '8' => '11101001100', '9' => '11100101100', ':' => '11100100110', ';' => '11101100100',
        '<' => '11100110100', '=' => '11100110010', '>' => '11011011000', '?' => '11011000110',
        '@' => '11000110110', 'A' => '10100011000', 'B' => '10001011000', 'C' => '10001000110',
        'D' => '10110001000', 'E' => '10001101000', 'F' => '10001100010', 'G' => '11010001000',
        'H' => '11000101000', 'I' => '11000100010', 'J' => '10110111000', 'K' => '10110001110',
        'L' => '10001101110', 'M' => '10111011000', 'N' => '10111000110', 'O' => '10001110110',
        'P' => '11101110110', 'Q' => '11010001110', 'R' => '11000101110', 'S' => '11011101000',
        'T' => '11011100010', 'U' => '11011101110', 'V' => '11101011000', 'W' => '11101000110',
        'X' => '11100010110', 'Y' => '11101101000', 'Z' => '11101100010', '[' => '11100011010',
        '\\' => '11101111010', ']' => '11001000010', '^' => '11110001010', '_' => '10100110000',
    ];
}

/**
 * Get Code 128 value for checksum calculation
 */
function getCode128Value($char) {
    $values = [
        ' ' => 0, '!' => 1, '"' => 2, '#' => 3, '$' => 4, '%' => 5, '&' => 6, "'" => 7,
        '(' => 8, ')' => 9, '*' => 10, '+' => 11, ',' => 12, '-' => 13, '.' => 14, '/' => 15,
        '0' => 16, '1' => 17, '2' => 18, '3' => 19, '4' => 20, '5' => 21, '6' => 22, '7' => 23,
        '8' => 24, '9' => 25, ':' => 26, ';' => 27, '<' => 28, '=' => 29, '>' => 30, '?' => 31,
        '@' => 32, 'A' => 33, 'B' => 34, 'C' => 35, 'D' => 36, 'E' => 37, 'F' => 38, 'G' => 39,
        'H' => 40, 'I' => 41, 'J' => 42, 'K' => 43, 'L' => 44, 'M' => 45, 'N' => 46, 'O' => 47,
        'P' => 48, 'Q' => 49, 'R' => 50, 'S' => 51, 'T' => 52, 'U' => 53, 'V' => 54, 'W' => 55,
        'X' => 56, 'Y' => 57, 'Z' => 58, '[' => 59, '\\' => 60, ']' => 61, '^' => 62, '_' => 63,
    ];
    return $values[$char] ?? 0;
}

/**
 * Get checksum pattern
 */
function getChecksumPattern($value) {
    $patterns = [
        0 => '11011001100', 1 => '11001101100', 2 => '11001100110', 3 => '10010011000',
        4 => '10010001100', 5 => '10001001100', 6 => '10011001000', 7 => '10011000100',
        8 => '10001100100', 9 => '11001001000', 10 => '11001000100', 11 => '11000100100',
        12 => '10110011100', 13 => '10011011100', 14 => '10011001110', 15 => '10111001100',
        16 => '10011101100', 17 => '10011100110', 18 => '11001110010', 19 => '11001011100',
        20 => '11001001110', 21 => '11011100100', 22 => '11001110100', 23 => '11101101110',
        24 => '11101001100', 25 => '11100101100', 26 => '11100100110', 27 => '11101100100',
        28 => '11100110100', 29 => '11100110010', 30 => '11011011000', 31 => '11011000110',
        32 => '11000110110', 33 => '10100011000', 34 => '10001011000', 35 => '10001000110',
        36 => '10110001000', 37 => '10001101000', 38 => '10001100010', 39 => '11010001000',
        40 => '11000101000', 41 => '11000100010', 42 => '10110111000', 43 => '10110001110',
        44 => '10001101110', 45 => '10111011000', 46 => '10111000110', 47 => '10001110110',
        48 => '11101110110', 49 => '11010001110', 50 => '11000101110', 51 => '11011101000',
        52 => '11011100010', 53 => '11011101110', 54 => '11101011000', 55 => '11101000110',
        56 => '11100010110', 57 => '11101101000', 58 => '11101100010', 59 => '11100011010',
        60 => '11101111010', 61 => '11001000010', 62 => '11110001010', 63 => '10100110000',
        64 => '10100001100', 65 => '10010110000', 66 => '10010000110', 67 => '10000101100',
        68 => '10000100110', 69 => '10110010000', 70 => '10110000100', 71 => '10011010000',
        72 => '10011000010', 73 => '10000110100', 74 => '10000110010', 75 => '11000010010',
        76 => '11001010000', 77 => '11110111010', 78 => '11000010100', 79 => '10001111010',
        80 => '10100111100', 81 => '10010111100', 82 => '10010011110', 83 => '10111100100',
        84 => '10011110100', 85 => '10011110010', 86 => '11110100100', 87 => '11110010100',
        88 => '11110010010', 89 => '11011011110', 90 => '11011110110', 91 => '11110110110',
        92 => '10101111000', 93 => '10100011110', 94 => '10001011110', 95 => '10111101000',
        96 => '10111100010', 97 => '11110101000', 98 => '11110100010', 99 => '10111011110',
        100 => '10111101110', 101 => '11101011110', 102 => '11110101110', 103 => '11010000100',
        104 => '11010010000', 105 => '11010011100',
    ];
    return $patterns[$value] ?? '11011001100';
}

/**
 * Generate Code 128 barcode SVG
 */
function generateBarcode($data, $filename) {
    $patterns = getCode128Patterns();
    
    // Start Code B
    $startB = '11010010000';
    $stop = '1100011101011';
    
    // Build barcode pattern
    $pattern = $startB;
    $checksum = 104; // Start B value
    
    for ($i = 0; $i < strlen($data); $i++) {
        $char = $data[$i];
        if (isset($patterns[$char])) {
            $pattern .= $patterns[$char];
            $checksum += getCode128Value($char) * ($i + 1);
        } else {
            // For digits, use the pattern
            $pattern .= $patterns[$char] ?? '11011001100';
            $checksum += (ord($char) - 32) * ($i + 1);
        }
    }
    
    // Add checksum
    $checksumValue = $checksum % 103;
    $pattern .= getChecksumPattern($checksumValue);
    
    // Add stop pattern
    $pattern .= $stop;
    
    // Generate SVG
    $barWidth = 2;
    $height = 60;
    $padding = 20;
    $width = strlen($pattern) * $barWidth + ($padding * 2);
    $totalHeight = $height + 30;
    
    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $totalHeight . '" viewBox="0 0 ' . $width . ' ' . $totalHeight . '">' . "\n";
    $svg .= '<rect width="100%" height="100%" fill="white"/>' . "\n";
    
    $x = $padding;
    for ($i = 0; $i < strlen($pattern); $i++) {
        if ($pattern[$i] === '1') {
            $svg .= '<rect x="' . $x . '" y="10" width="' . $barWidth . '" height="' . $height . '" fill="black"/>' . "\n";
        }
        $x += $barWidth;
    }
    
    // Add text label
    $textX = $width / 2;
    $svg .= '<text x="' . $textX . '" y="' . ($height + 25) . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" fill="black">' . htmlspecialchars($data) . '</text>' . "\n";
    $svg .= '</svg>';
    
    // Ensure storage directory exists
    $storageDir = __DIR__ . '/../storage/barcodes';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    
    // Save file
    $filename = preg_replace('/\.(png|svg)$/', '', $filename) . '.svg';
    $filepath = $storageDir . '/' . $filename;
    file_put_contents($filepath, $svg);
    
    return 'storage/barcodes/' . $filename;
}

/**
 * Generate QR code for student using LRN
 */
function generateStudentBarcode($studentId) {
    $filename = 'student_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentId) . '.svg';
    return generateQRCode($studentId, $filename);
}

/**
 * Regenerate barcode (delete old, create new)
 */
function regenerateStudentBarcode($studentId, $oldBarcodePath = null) {
    if ($oldBarcodePath) {
        deleteBarcode($oldBarcodePath);
    }
    return generateStudentBarcode($studentId);
}

/**
 * Delete barcode file
 */
function deleteBarcode($barcodePath) {
    if (!$barcodePath) return false;
    $fullPath = __DIR__ . '/../' . $barcodePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Check if barcode file exists
 */
function barcodeExists($barcodePath) {
    if (!$barcodePath) return false;
    return file_exists(__DIR__ . '/../' . $barcodePath);
}

/**
 * Get expected barcode path for student
 */
function getBarcodePath($studentId) {
    return 'storage/barcodes/student_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentId) . '.svg';
}
