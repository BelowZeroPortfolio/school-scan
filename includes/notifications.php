<?php
/**
 * Notification Functions
 * Send parent notifications via SMS (SMS Mobile API)
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
 * Send SMS notification via SMS Mobile API
 * 
 * @param string $phone Recipient phone number
 * @param string $message SMS message
 * @return array Result with success status and details
 */
function sendSmsNotification($phone, $message) {
    $result = ['success' => false, 'error' => null, 'response' => null];
    
    try {
        $apiKey = config('smsmobileapi_key');
        
        if (!$apiKey) {
            $result['error'] = 'SMS Mobile API key not configured';
            return $result;
        }
        
        $url = 'https://api.smsmobileapi.com/sendsms/';
        
        $data = [
            'apikey' => $apiKey,
            'recipients' => $phone,
            'message' => $message
        ];
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $result['error'] = 'Failed to connect to SMS Mobile API';
            return $result;
        }
        
        $result['response'] = $response;
        $responseData = json_decode($response, true);
        
        if (isset($responseData['success']) && $responseData['success'] === false) {
            $result['error'] = $responseData['error'] ?? 'SMS send failed';
            return $result;
        }
        
        $result['success'] = true;
        
        if (function_exists('logInfo')) {
            logInfo('SMS notification sent', ['recipient' => $phone]);
        }
        
        return $result;
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        
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
