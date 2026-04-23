<?php
// ============================================================
// Stakeholder Map v1.1 – Auth API (Magiclink)
// ============================================================
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── Request Magic Link ───────────────────────────────────
    case 'request':
        $input = getJsonInput();
        $email = strtolower(trim($input['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Bitte gib eine gueltige E-Mail-Adresse ein.');
        }

        $db = getDB();

        // Create user if not exists
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $db->prepare('INSERT INTO users (email) VALUES (?)');
            $stmt->execute([$email]);
            $userId = $db->lastInsertId();
        } else {
            $userId = $user['id'];
        }

        // Invalidate old magic links
        $db->prepare('UPDATE magic_links SET used = 1 WHERE user_id = ? AND used = 0')
           ->execute([$userId]);

        // Create new magic link
        $token = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + MAGIC_LINK_DURATION);

        $stmt = $db->prepare('INSERT INTO magic_links (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $token, $expiresAt]);

        // Build magic link URL
        $magicUrl = API_URL . '/auth.php?action=verify&token=' . $token;

        // Send email
        $subject = 'Dein Login-Link fuer Stakeholder Map';
        $body = "Hallo!\n\n"
              . "Klicke auf den folgenden Link, um dich bei Stakeholder Map einzuloggen:\n\n"
              . $magicUrl . "\n\n"
              . "Der Link ist 15 Minuten gueltig.\n\n"
              . "Falls du diesen Login nicht angefordert hast, kannst du diese E-Mail ignorieren.\n\n"
              . "Viele Gruesse\n"
              . "Dein Stakeholder Map Team";

        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n"
                 . "Reply-To: " . MAIL_FROM . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "X-Mailer: StakeholderMap/1.1";

        $mailSent = mail($email, $subject, $body, $headers);

        if (!$mailSent) {
            jsonError('E-Mail konnte nicht gesendet werden. Bitte versuche es erneut.', 500);
        }

        jsonResponse(['success' => true, 'message' => 'Login-Link wurde gesendet!']);
        break;

    // ── Verify Magic Link ────────────────────────────────────
    case 'verify':
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            jsonError('Ungueltiger Link.');
        }

        $db = getDB();

        $stmt = $db->prepare('
            SELECT ml.id, ml.user_id, ml.expires_at, ml.used, u.email, u.name
            FROM magic_links ml
            JOIN users u ON u.id = ml.user_id
            WHERE ml.token = ?
        ');
        $stmt->execute([$token]);
        $link = $stmt->fetch();

        if (!$link) {
            // Redirect to app with error
            header('Location: ' . APP_URL . '/stakeholder-manager.html?auth=invalid');
            exit;
        }

        if ($link['used']) {
            header('Location: ' . APP_URL . '/stakeholder-manager.html?auth=used');
            exit;
        }

        if (strtotime($link['expires_at']) < time()) {
            header('Location: ' . APP_URL . '/stakeholder-manager.html?auth=expired');
            exit;
        }

        // Mark as used
        $db->prepare('UPDATE magic_links SET used = 1 WHERE id = ?')
           ->execute([$link['id']]);

        // Update last login
        $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
           ->execute([$link['user_id']]);

        // Create session
        $sessionToken = generateToken();
        $sessionExpires = date('Y-m-d H:i:s', time() + SESSION_DURATION);

        $db->prepare('INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)')
           ->execute([$link['user_id'], $sessionToken, $sessionExpires]);

        // Redirect to app with session token
        header('Location: ' . APP_URL . '/stakeholder-manager.html?auth=success&session=' . $sessionToken);
        exit;
        break;

    // ── Check Session ────────────────────────────────────────
    case 'me':
        $user = requireAuth();

        $db = getDB();
        $stmt = $db->prepare('SELECT name, email, wohnort, arbeitsort FROM users WHERE id = ?');
        $stmt->execute([$user['user_id']]);
        $profile = $stmt->fetch();

        jsonResponse(['user' => $profile]);
        break;

    // ── Update Profile ───────────────────────────────────────
    case 'profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonError('Methode nicht erlaubt', 405);
        }

        $user = requireAuth();
        $input = getJsonInput();
        $db = getDB();

        $stmt = $db->prepare('UPDATE users SET name = ?, wohnort = ?, arbeitsort = ? WHERE id = ?');
        $stmt->execute([
            $input['name'] ?? null,
            $input['wohnort'] ?? null,
            $input['arbeitsort'] ?? null,
            $user['user_id']
        ]);

        jsonResponse(['success' => true]);
        break;

    // ── Logout ───────────────────────────────────────────────
    case 'logout':
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            $db = getDB();
            $db->prepare('DELETE FROM sessions WHERE token = ?')->execute([$m[1]]);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Unbekannte Aktion', 404);
}
