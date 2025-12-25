<?php
/**
 * Notification Functions
 * Send parent notifications via SMS (Semaphore API - Philippines)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Format attendance notification message
 * 
 * @param array $student Student data
 * @param string $status Attendance status
 * @param string $timestamp Timestamp
 * @return string Formatted message
 */
function formatAttendanceMessage($student, $status = 'present', $timestamp = null) {
    // Use Philippines timezone (UTC+8)
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);
    $formattedTime = $now->format('M d, Y h:i A');
    
    $studentName = $student['first_name'] . ' ' . $student['last_name'];
    
    $statusText = [
        'present' => 'arrived at school',
        'late' => 'arrived late at school',
        'absent' => 'was marked absent'
    ];
    
    $action = $statusText[$status] ?? 'checked in';
    $schoolName = config('school_name', 'School');
    
    $message = sprintf(
        "Hello %s, your child %s %s on %s. - %s",
        $student['parent_name'] ?? 'Parent/Guardian',
        $studentName,
        $action,
        $formattedTime,
        $schoolName
    );
    
    return $message;
}

/**
 * Format phone number for Semaphore (Philippine format)
 * Semaphore accepts both 09xx and 639xx formats
 * 
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function formatPhoneForSemaphore($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, convert to 63 (international format)
    if (substr($phone, 0, 1) === '0') {
        $phone = '63' . substr($phone, 1);
    }
    
    // If 10 digits starting with 9, add 63 prefix
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
        $phone = '63' . $phone;
    }
    
    return $phone;
}

/**
 * Send SMS notification via Semaphore API
 * 
 * @param string $phone Recipient phone number
 * @param string $message SMS message
 * @return array Result with success status and details
 */
function sendSmsNotification($phone, $message) {
    $result = ['success' => false, 'error' => null, 'response' => null];
    
    try {
        $apiKey = config('semaphore_api_key');
        $senderName = config('semaphore_sender', 'SEMAPHORE');
        
        if (!$apiKey) {
            $result['error'] = 'Semaphore API key not configured';
            error_log('[SMS] Error: Semaphore API key not configured');
            return $result;
        }
        
        // Format phone number for Philippine format (09xx)
        $formattedPhone = formatPhoneForSemaphore($phone);
        
        // Log the attempt
        error_log('[SMS] Sending to: ' . $formattedPhone . ' | Original: ' . $phone);
        
        $url = 'https://semaphore.co/api/v4/messages';
        
        $parameters = [
            'apikey' => $apiKey,
            'number' => $formattedPhone,
            'message' => $message,
            'sendername' => $senderName
        ];
        
        // Log request (without API key)
        error_log('[SMS] Request URL: ' . $url);
        error_log('[SMS] Sender: ' . $senderName);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log response
        error_log('[SMS] HTTP Code: ' . $httpCode);
        error_log('[SMS] Response: ' . $response);
        
        if ($curlError) {
            $result['error'] = 'Connection error: ' . $curlError;
            error_log('[SMS] cURL Error: ' . $curlError);
            return $result;
        }
        
        $result['response'] = $response;
        $responseData = json_decode($response, true);
        
        // Check HTTP status code
        if ($httpCode >= 200 && $httpCode < 300) {
            // Check Semaphore response
            if (is_array($responseData) && !empty($responseData)) {
                // Check if it's an error response
                if (isset($responseData['error'])) {
                    $result['error'] = $responseData['error'];
                    error_log('[SMS] API Error: ' . $responseData['error']);
                    return $result;
                }
                
                // Success - Semaphore returns array of message objects
                $firstMessage = $responseData[0] ?? $responseData;
                $status = $firstMessage['status'] ?? '';
                $messageId = $firstMessage['message_id'] ?? 'N/A';
                
                error_log('[SMS] Message ID: ' . $messageId . ' | Status: ' . $status);
                
                if (in_array($status, ['Queued', 'Pending', 'Sent', 'Success'])) {
                    $result['success'] = true;
                    error_log('[SMS] SUCCESS - Message queued/sent');
                    
                    if (function_exists('logInfo')) {
                        logInfo('SMS notification sent via Semaphore', [
                            'recipient' => $formattedPhone,
                            'messageId' => $messageId,
                            'status' => $status
                        ]);
                    }
                } else if ($status === 'Failed') {
                    $result['error'] = 'Message failed to send - Status: Failed';
                    error_log('[SMS] FAILED - Message status is Failed');
                } else {
                    // Assume success if we got a response with message_id
                    if (isset($firstMessage['message_id'])) {
                        $result['success'] = true;
                        error_log('[SMS] SUCCESS - Got message_id');
                    } else {
                        $result['error'] = 'Unknown status: ' . $status;
                        error_log('[SMS] Unknown status: ' . $status);
                    }
                }
            } else {
                $result['error'] = 'Invalid response from Semaphore';
                error_log('[SMS] Invalid response format');
            }
        } else {
            // Handle error response
            $errorMsg = 'HTTP ' . $httpCode;
            if (isset($responseData['error'])) {
                $errorMsg = $responseData['error'];
            } elseif (isset($responseData['message'])) {
                $errorMsg = $responseData['message'];
            }
            $result['error'] = $errorMsg;
            error_log('[SMS] HTTP Error: ' . $errorMsg);
        }
        
        return $result;
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        error_log('[SMS] Exception: ' . $e->getMessage());
        
        if (function_exists('logError')) {
            logError('SMS notification failed: ' . $e->getMessage(), ['recipient' => $phone]);
        }
        
        return $result;
    }
}

/**
 * Log notification attempt to database
 */
function logNotification($studentId, $recipient, $message, $success, $errorMessage = null) {
    try {
        $tableCheck = @dbFetchOne("SHOW TABLES LIKE 'notification_logs'");
        if (!$tableCheck) return;
        
        $status = $success ? 'sent' : 'failed';
        $sentAt = $success ? date('Y-m-d H:i:s') : null;
        
        $sql = "INSERT INTO notification_logs 
                (student_id, notification_type, recipient, message, status, sent_at, error_message)
                VALUES (?, 'sms', ?, ?, ?, ?, ?)";
        
        dbInsert($sql, [$studentId, $recipient, $message, $status, $sentAt, $errorMessage]);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Check if SMS is enabled for a specific student
 * 
 * @param array $student Student data (must include sms_enabled field)
 * @return bool True if SMS is enabled for this student
 */
function isStudentSmsEnabled($student): bool {
    return isset($student['sms_enabled']) && (int)$student['sms_enabled'] === 1;
}

/**
 * Send attendance notification with detailed status
 * 
 * @param array $student Student data
 * @param string $status Attendance status
 * @return array Detailed notification result
 */
function sendAttendanceNotificationWithStatus($student, $status = 'present') {
    $result = [
        'sms' => ['attempted' => false, 'success' => false, 'error' => null, 'recipient' => null]
    ];
    
    // Check if SMS is enabled for this student (paid subscription)
    if (!isStudentSmsEnabled($student)) {
        $result['sms']['error'] = 'SMS not enabled for this student';
        return $result;
    }
    
    $message = formatAttendanceMessage($student, $status);
    
    if (empty($student['parent_phone'])) {
        $result['sms']['error'] = 'No parent phone number configured';
        return $result;
    }
    
    $result['sms']['recipient'] = $student['parent_phone'];
    
    if (!validatePhone($student['parent_phone'])) {
        $result['sms']['error'] = 'Invalid phone number format';
        return $result;
    }
    
    $result['sms']['attempted'] = true;
    $smsResult = sendSmsNotification($student['parent_phone'], $message);
    $result['sms']['success'] = $smsResult['success'];
    $result['sms']['error'] = $smsResult['error'];
    $result['sms']['response'] = $smsResult['response'] ?? null;
    
    // Log to database
    logNotification(
        $student['id'],
        $student['parent_phone'],
        $message,
        $smsResult['success'],
        $smsResult['error']
    );
    
    return $result;
}

/**
 * Send attendance notification (simple version)
 * 
 * @param array $student Student data
 * @param string $status Attendance status
 * @return bool True if notification sent
 */
function sendAttendanceNotification($student, $status = 'present') {
    $result = sendAttendanceNotificationWithStatus($student, $status);
    return $result['sms']['success'];
}

/**
 * Format dismissal notification message
 * 
 * @param array $student Student data
 * @param string $timestamp Timestamp
 * @return string Formatted message
 */
function formatDismissalMessage($student, $timestamp = null) {
    // Use Philippines timezone (UTC+8)
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);
    $formattedTime = $now->format('M d, Y h:i A');
    
    $studentName = $student['first_name'] . ' ' . $student['last_name'];
    $schoolName = config('school_name', 'School');
    
    $message = sprintf(
        "Hello %s, your child %s has been dismissed from school on %s. - %s",
        $student['parent_name'] ?? 'Parent/Guardian',
        $studentName,
        $formattedTime,
        $schoolName
    );
    
    return $message;
}

/**
 * Send dismissal notification with detailed status
 * 
 * @param array $student Student data
 * @return array Detailed notification result
 */
function sendDismissalNotificationWithStatus($student) {
    $result = [
        'sms' => ['attempted' => false, 'success' => false, 'error' => null, 'recipient' => null]
    ];
    
    // Check if SMS is enabled for this student (paid subscription)
    if (!isStudentSmsEnabled($student)) {
        $result['sms']['error'] = 'SMS not enabled for this student';
        return $result;
    }
    
    $message = formatDismissalMessage($student);
    
    if (empty($student['parent_phone'])) {
        $result['sms']['error'] = 'No parent phone number configured';
        return $result;
    }
    
    $result['sms']['recipient'] = $student['parent_phone'];
    
    if (!validatePhone($student['parent_phone'])) {
        $result['sms']['error'] = 'Invalid phone number format';
        return $result;
    }
    
    $result['sms']['attempted'] = true;
    $smsResult = sendSmsNotification($student['parent_phone'], $message);
    $result['sms']['success'] = $smsResult['success'];
    $result['sms']['error'] = $smsResult['error'];
    $result['sms']['response'] = $smsResult['response'] ?? null;
    
    // Log to database
    logNotification(
        $student['id'],
        $student['parent_phone'],
        $message,
        $smsResult['success'],
        $smsResult['error']
    );
    
    return $result;
}

/**
 * Send dismissal notification (simple version)
 * 
 * @param array $student Student data
 * @return bool True if notification sent
 */
function sendDismissalNotification($student) {
    $result = sendDismissalNotificationWithStatus($student);
    return $result['sms']['success'];
}
