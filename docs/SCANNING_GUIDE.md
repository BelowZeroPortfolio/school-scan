# Barcode Scanning Guide

## Overview

The attendance scanning system supports two methods of barcode scanning:
1. **Hardware Scanner (Keyboard Wedge)** - Traditional barcode scanner device
2. **Camera Scanner** - Use device camera with QuaggaJS library

## Hardware Scanner Setup

### Requirements
- USB barcode scanner configured as keyboard wedge
- Scanner should be set to CODE128 format (or compatible format)
- Scanner should append Enter/Return after scan

### Usage
1. Navigate to the Scan page (`/pages/scan.php`)
2. Ensure the barcode input field is focused (it auto-focuses)
3. Scan the student barcode
4. The system will automatically submit and process the scan
5. Visual feedback will be displayed (purple for success, orange for errors)

### Features
- **Auto-submit**: Scans are automatically submitted after detection
- **Auto-focus**: Input field maintains focus for continuous scanning
- **Duplicate prevention**: Students can only be scanned once per day
- **Real-time feedback**: Immediate visual confirmation of scan results

## Camera Scanner Setup

### Requirements
- Device with camera (webcam or mobile camera)
- Modern browser with camera access support
- HTTPS connection (required for camera access in production)

### Usage
1. Navigate to the Scan page (`/pages/scan.php`)
2. Click "Start Camera" button
3. Allow camera access when prompted
4. Position the barcode within the camera view
5. The system will automatically detect and process the barcode
6. Camera stops automatically after successful scan

### Supported Barcode Formats
- CODE128 (primary)
- EAN-13, EAN-8
- UPC-A, UPC-E
- Code 39
- Codabar
- Interleaved 2 of 5

## Attendance Recording Process

When a barcode is scanned:

1. **Barcode Validation**: System looks up student by barcode value
2. **Duplicate Check**: Verifies student hasn't been scanned today
3. **Record Creation**: Creates attendance record with timestamp
4. **Notification**: Sends automatic notification to parent (email/SMS)
5. **Visual Feedback**: Displays success or error message

## Error Handling

### Common Errors

**"Student not found"**
- Barcode doesn't match any active student
- Check if student exists in system
- Verify barcode was generated correctly

**"Attendance already recorded"**
- Student was already scanned today
- Duplicate prevention is working correctly
- Check attendance history to verify

**"Failed to record attendance"**
- Database error occurred
- Check system logs for details
- Contact administrator

## Notifications

After successful scan, the system automatically:
- Formats notification message with student name, timestamp, and status
- Sends email to parent_email (if configured)
- Sends SMS to parent_phone (if configured)
- Logs notification attempts
- Queues failed notifications for retry

## Today's Statistics

The scan page displays real-time statistics:
- **Total Students**: Number of active students
- **Present**: Students scanned today
- **Late**: Students marked as late
- **Attendance Rate**: Percentage of students present

## Recent Scans

The scan page shows today's attendance records in real-time:
- Check-in time
- Student ID and name
- Class and section
- Attendance status

## Tips for Best Results

### Hardware Scanner
- Keep scanner within 6-12 inches of barcode
- Ensure barcode is clean and undamaged
- Scan in well-lit conditions
- Hold scanner steady during scan

### Camera Scanner
- Use good lighting conditions
- Hold barcode flat and steady
- Position barcode to fill most of camera view
- Avoid glare or reflections on barcode
- Keep camera lens clean

## Troubleshooting

### Hardware Scanner Not Working
1. Check USB connection
2. Verify scanner is in keyboard wedge mode
3. Test scanner in text editor (should type barcode value)
4. Ensure input field has focus

### Camera Scanner Not Working
1. Check browser camera permissions
2. Verify HTTPS connection (required for camera access)
3. Try different browser (Chrome/Firefox recommended)
4. Check if camera is being used by another application
5. Ensure adequate lighting

### Notifications Not Sending
1. Check SMTP/Twilio credentials in config
2. Verify parent contact information is correct
3. Check notification logs for error details
4. Failed notifications are queued for automatic retry

## Security Features

- **CSRF Protection**: All form submissions are protected
- **Authentication Required**: Only admin/operator roles can scan
- **Session Validation**: Active session required
- **Input Sanitization**: All barcode input is sanitized
- **SQL Injection Prevention**: Prepared statements used throughout

## Performance

- Scan processing: < 500ms typical
- Notification sending: Asynchronous (doesn't block scan)
- Failed notifications: Queued for background retry
- Database queries: Optimized with indexes

## Related Pages

- **Dashboard** (`/pages/dashboard.php`) - View overall statistics
- **Attendance History** (`/pages/attendance-history.php`) - View past records
- **Students** (`/pages/students.php`) - Manage student records
- **Reports** (`/pages/reports.php`) - Generate attendance reports
