<?php
/**
 * Barcode and QR Code Generation Functions
 * Generates Code 128 barcodes and QR codes for student ID cards
 */

if (!isset($GLOBALS['config'])) {
    require_once __DIR__ . '/../config/config.php';
}

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
    
    // Generate QR code using simple PHP implementation
    $qr = generateQRMatrix($data);
    $svg = renderQRtoSVG($qr, $data);
    
    file_put_contents($filepath, $svg);
    return 'storage/barcodes/' . $filename;
}

/**
 * Generate QR code matrix (simplified implementation)
 */
function generateQRMatrix($data) {
    // Use a simple numeric encoding for LRN (12 digits)
    $size = 25; // QR code size
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    
    // Add finder patterns (corners)
    addFinderPattern($matrix, 0, 0);
    addFinderPattern($matrix, $size - 7, 0);
    addFinderPattern($matrix, 0, $size - 7);
    
    // Add timing patterns
    for ($i = 8; $i < $size - 8; $i++) {
        $matrix[$i][6] = ($i % 2 == 0) ? 1 : 0;
        $matrix[6][$i] = ($i % 2 == 0) ? 1 : 0;
    }
    
    // Encode data in remaining space
    $binary = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }
    
    $pos = 0;
    for ($y = $size - 1; $y >= 0; $y -= 2) {
        if ($y == 6) $y = 5; // Skip timing pattern
        for ($x = $size - 1; $x >= 0; $x--) {
            for ($dx = 0; $dx <= 1; $dx++) {
                $col = $y - $dx;
                if ($col < 0) continue;
                if (!isReserved($x, $col, $size)) {
                    if ($pos < strlen($binary)) {
                        $matrix[$x][$col] = $binary[$pos] == '1' ? 1 : 0;
                        $pos++;
                    }
                }
            }
        }
    }
    
    return $matrix;
}

function addFinderPattern(&$matrix, $x, $y) {
    $pattern = [
        [1,1,1,1,1,1,1],
        [1,0,0,0,0,0,1],
        [1,0,1,1,1,0,1],
        [1,0,1,1,1,0,1],
        [1,0,1,1,1,0,1],
        [1,0,0,0,0,0,1],
        [1,1,1,1,1,1,1]
    ];
    for ($i = 0; $i < 7; $i++) {
        for ($j = 0; $j < 7; $j++) {
            if (isset($matrix[$x + $i][$y + $j])) {
                $matrix[$x + $i][$y + $j] = $pattern[$i][$j];
            }
        }
    }
}

function isReserved($x, $y, $size) {
    // Finder patterns
    if ($x < 8 && $y < 8) return true;
    if ($x < 8 && $y >= $size - 8) return true;
    if ($x >= $size - 8 && $y < 8) return true;
    // Timing patterns
    if ($x == 6 || $y == 6) return true;
    return false;
}

function renderQRtoSVG($matrix, $data) {
    $size = count($matrix);
    $cellSize = 8;
    $padding = 20;
    $width = $size * $cellSize + $padding * 2;
    $height = $width;
    
    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">' . "\n";
    $svg .= '<rect width="100%" height="100%" fill="white"/>' . "\n";
    
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($matrix[$y][$x] == 1) {
                $px = $padding + $x * $cellSize;
                $py = $padding + $y * $cellSize;
                $svg .= '<rect x="' . $px . '" y="' . $py . '" width="' . $cellSize . '" height="' . $cellSize . '" fill="black"/>' . "\n";
            }
        }
    }
    
    $svg .= '</svg>';
    return $svg;
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
 * Generate barcode for student using LRN
 */
function generateStudentBarcode($studentId) {
    $filename = 'student_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentId) . '.svg';
    return generateBarcode($studentId, $filename);
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
