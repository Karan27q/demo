<?php
/**
 * API Helper Functions
 * Ensures all API endpoints return JSON, even on errors
 */

// Start output buffering to catch any warnings/errors
if (!ob_get_level()) {
    ob_start();
}

// Set JSON header FIRST before any includes or output
header('Content-Type: application/json');

// Disable error display in production (errors will be returned as JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to return JSON error and exit
function returnJsonError($message, $code = 500) {
    http_response_code($code);
    ob_clean(); // Clear any output
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Function to return JSON success
function returnJsonSuccess($data = null, $message = null) {
    ob_clean(); // Clear any output
    $response = ['success' => true];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message !== null) {
        $response['message'] = $message;
    }
    echo json_encode($response);
    exit();
}

// Set error handler to catch fatal errors
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        returnJsonError("PHP Error: $message in $file on line $line", 500);
    }
    return false;
});

// Set exception handler
set_exception_handler(function($exception) {
    returnJsonError("Uncaught exception: " . $exception->getMessage(), 500);
});

