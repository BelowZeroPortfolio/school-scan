/**
 * Barcode Scanner JavaScript
 * Handles hardware scanner (keyboard wedge) and camera-based scanning
 */

// Hardware Scanner (Keyboard Wedge) Handler
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('barcode');
    const scanForm = document.getElementById('scanForm');
    
    if (barcodeInput && scanForm) {
        let scanBuffer = '';
        let scanTimeout = null;
        
        // Auto-submit when barcode is scanned
        barcodeInput.addEventListener('input', function(e) {
            // Clear previous timeout
            if (scanTimeout) {
                clearTimeout(scanTimeout);
            }
            
            // Set timeout to detect end of scan
            // Most barcode scanners input very quickly (< 100ms)
            scanTimeout = setTimeout(function() {
                const value = barcodeInput.value.trim();
                
                // If we have a value, submit the form
                if (value.length > 0) {
                    scanForm.submit();
                }
            }, 100);
        });
        
        // Keep focus on input field
        barcodeInput.addEventListener('blur', function() {
            setTimeout(function() {
                barcodeInput.focus();
            }, 100);
        });
        
        // Clear input after form submission
        scanForm.addEventListener('submit', function() {
            setTimeout(function() {
                barcodeInput.value = '';
                barcodeInput.focus();
            }, 100);
        });
    }
});

// Camera Scanner using QuaggaJS
function cameraScanner() {
    return {
        scanning: false,
        
        startScanning() {
            this.scanning = true;
            
            // Initialize QuaggaJS
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.querySelector('#camera-preview'),
                    constraints: {
                        width: 640,
                        height: 480,
                        facingMode: "environment" // Use back camera on mobile
                    }
                },
                decoder: {
                    readers: [
                        "code_128_reader",
                        "ean_reader",
                        "ean_8_reader",
                        "code_39_reader",
                        "code_39_vin_reader",
                        "codabar_reader",
                        "upc_reader",
                        "upc_e_reader",
                        "i2of5_reader"
                    ],
                    debug: {
                        drawBoundingBox: true,
                        showFrequency: false,
                        drawScanline: true,
                        showPattern: false
                    }
                },
                locate: true,
                locator: {
                    patchSize: "medium",
                    halfSample: true
                },
                numOfWorkers: 4,
                frequency: 10
            }, function(err) {
                if (err) {
                    console.error('QuaggaJS initialization error:', err);
                    alert('Failed to start camera: ' + err.message);
                    return;
                }
                
                console.log('QuaggaJS initialized successfully');
                Quagga.start();
            });
            
            // Handle detected barcodes
            Quagga.onDetected(this.onBarcodeDetected.bind(this));
        },
        
        stopScanning() {
            this.scanning = false;
            Quagga.stop();
            Quagga.offDetected(this.onBarcodeDetected);
        },
        
        onBarcodeDetected(result) {
            if (!result || !result.codeResult) {
                return;
            }
            
            const code = result.codeResult.code;
            console.log('Barcode detected:', code);
            
            // Stop scanning
            this.stopScanning();
            
            // Fill the barcode input and submit
            const barcodeInput = document.getElementById('barcode');
            if (barcodeInput) {
                barcodeInput.value = code;
                
                // Submit the form
                const scanForm = document.getElementById('scanForm');
                if (scanForm) {
                    scanForm.submit();
                }
            }
        }
    };
}

// Make cameraScanner available globally for Alpine.js
window.cameraScanner = cameraScanner;

// Auto-refresh page after successful scan (optional)
// This keeps the scan page fresh and ready for next scan
document.addEventListener('DOMContentLoaded', function() {
    // Check if there was a successful scan
    const successAlert = document.querySelector('.bg-violet-100');
    
    if (successAlert) {
        // Auto-clear the message and refocus after 3 seconds
        setTimeout(function() {
            const barcodeInput = document.getElementById('barcode');
            if (barcodeInput) {
                barcodeInput.value = '';
                barcodeInput.focus();
            }
            
            // Optionally reload to clear the message
            // window.location.href = window.location.pathname;
        }, 3000);
    }
});
