<?php
// ============================================================
// Stakeholder Map – Configuration Template
// ============================================================
// ANLEITUNG:
// 1. Diese Datei kopieren: cp config.example.php config.php
// 2. Alle Platzhalter (YOUR_*) mit echten Werten befüllen
// 3. config.php NIEMALS in Git einchecken (.gitignore!)
// ============================================================

// --- Database ---
define('DB_HOST', 'YOUR_DB_HOST');           // z.B. database-xxx.webspace-host.com
define('DB_NAME', 'YOUR_DB_NAME');           // z.B. dbs12345678
define('DB_USER', 'YOUR_DB_USER');           // z.B. dbu1234567
define('DB_PASS', 'YOUR_DB_PASSWORD');       // Starkes Passwort verwenden

// --- App ---
define('APP_URL', 'https://your-domain.app');
define('API_URL', 'https://your-domain.app/api');

// --- Session ---
define('SESSION_DURATION', 30 * 24 * 3600); // 30 Tage
define('MAGIC_LINK_DURATION', 15 * 60);     // 15 Minuten

// --- Mail ---
define('MAIL_FROM', 'noreply@your-domain.app');
define('MAIL_FROM_NAME', 'Stakeholder Map');

// --- Response Header ---
header('Content-Type: application/json; charset=utf-8');

// --- Database Connection ---
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// --- Helpers ---
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['error' => $message], $code);
}

function getJsonInput(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

// --- Auth Middleware ---
function requireAuth(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (empty($header) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        jsonError('Nicht autorisiert', 401);
    }

    $token = $m[1];
    $db    = getDB();
    $stmt  = $db->prepare('
        SELECT s.user_id, u.email, u.name
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.token = ? AND s.expires_at > NOW()
    ');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonError('Session abgelaufen', 401);
    }

    return $user;
}
