<?php
/**
 * Centralized Error Handler for POS Dashboard
 * Provides consistent error handling and logging across the application
 */

class ErrorHandler {
    private static $logDir;
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$logDir = __DIR__ . '/logs/';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        // Set custom error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
        
        self::$initialized = true;
    }
    
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errorType,
            $message,
            $file,
            $line
        );
        
        self::writeLog('error', $logMessage);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    public static function handleException($exception) {
        $logMessage = sprintf(
            "[%s] Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        self::writeLog('exception', $logMessage);
        
        // Send user-friendly error response
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'An internal server error occurred. Please try again later.',
                'timestamp' => date('c')
            ]);
        }
    }
    
    public static function handleFatalError() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $logMessage = sprintf(
                "[%s] Fatal Error: %s in %s on line %d",
                date('Y-m-d H:i:s'),
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            self::writeLog('fatal', $logMessage);
        }
    }
    
    public static function logCustomError($message, $context = [], $level = 'error') {
        $contextStr = !empty($context) ? ' Context: ' . json_encode($context) : '';
        $logMessage = sprintf(
            "[%s] %s: %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $contextStr
        );
        
        self::writeLog($level, $logMessage);
    }
    
    private static function writeLog($type, $message) {
        $filename = self::$logDir . $type . '_' . date('Y-m-d') . '.log';
        file_put_contents($filename, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
                return 'Fatal Error';
            case E_WARNING:
                return 'Warning';
            case E_PARSE:
                return 'Parse Error';
            case E_NOTICE:
                return 'Notice';
            case E_CORE_ERROR:
                return 'Core Error';
            case E_CORE_WARNING:
                return 'Core Warning';
            case E_COMPILE_ERROR:
                return 'Compile Error';
            case E_COMPILE_WARNING:
                return 'Compile Warning';
            case E_USER_ERROR:
                return 'User Error';
            case E_USER_WARNING:
                return 'User Warning';
            case E_USER_NOTICE:
                return 'User Notice';
            case E_STRICT:
                return 'Strict Standards';
            case E_RECOVERABLE_ERROR:
                return 'Recoverable Error';
            case E_DEPRECATED:
                return 'Deprecated';
            case E_USER_DEPRECATED:
                return 'User Deprecated';
            default:
                return 'Unknown Error';
        }
    }
    
    public static function sendJsonError($message, $httpCode = 400, $details = null) {
        self::logCustomError($message, $details ? ['details' => $details] : []);
        
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json');
        }
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function sendJsonSuccess($message, $data = null, $httpCode = 200) {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json');
        }
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Initialize error handler
ErrorHandler::init();
?>

