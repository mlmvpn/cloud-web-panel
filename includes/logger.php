<?php

function log_event(string $level, string $message, array $context = []): void {
    try {
        $stmt = db()->prepare('INSERT INTO error_logs (level, user_id, message, context) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $level,
            current_user_id(),
            mb_substr($message, 0, 2000),
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
        ]);
    } catch (Throwable $e) {
        error_log('log_event failed: ' . $e->getMessage() . ' | original: ' . $message);
    }
}

function install_global_error_logging(): void {
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        log_event('php_warning', $errstr, [
            'file' => $errfile,
            'line' => $errline,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        return false;
    });

    set_exception_handler(function (Throwable $e) {
        log_event('php_exception', $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => mb_substr($e->getTraceAsString(), 0, 1500),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        if (headers_sent()) {
            return;
        }
        if (defined('IS_API')) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'خطای غیرمنتظره‌ای رخ داد. کمی بعد دوباره تلاش کنید.']);
        } else {
            http_response_code(500);
            echo 'یک خطای غیرمنتظره رخ داد. لطفاً کمی بعد دوباره تلاش کنید.';
        }
    });

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) {
            return;
        }
        try {
            log_event('php_fatal', $err['message'], [
                'file' => $err['file'],
                'line' => $err['line'],
                'url' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
        } catch (Throwable $e) {
            error_log('shutdown log_event failed: ' . $e->getMessage());
        }
        if (!headers_sent()) {
            if (defined('IS_API')) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'خطای سرور رخ داد. این مورد ثبت شد.']);
            }
        }
    });
}
