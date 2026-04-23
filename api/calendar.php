<?php
// ============================================================
// Stakeholder Map v1.2 – Calendar Entries API
// ============================================================
require_once __DIR__ . '/config.php';

$user = requireAuth();
$userId = $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($method) {

    // ── GET: List calendar entries ──────────────────────────
    case 'GET':
        if ($action === 'list') {
            $db = getDB();
            // Optional date range filter
            $from = $_GET['from'] ?? null;
            $to = $_GET['to'] ?? null;

            if ($from && $to) {
                $stmt = $db->prepare('
                    SELECT * FROM calendar_entries
                    WHERE user_id = ? AND entry_date >= ? AND entry_date <= ?
                    ORDER BY entry_date ASC, entry_time ASC
                ');
                $stmt->execute([$userId, $from, $to]);
            } else {
                $stmt = $db->prepare('
                    SELECT * FROM calendar_entries
                    WHERE user_id = ?
                    ORDER BY entry_date ASC, entry_time ASC
                ');
                $stmt->execute([$userId]);
            }

            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['kontakte'] = json_decode($row['kontakte'] ?? '[]', true) ?: [];
                $row['tags'] = json_decode($row['tags'] ?? '[]', true) ?: [];
                $row['erledigt'] = (bool)$row['erledigt'];
                unset($row['user_id']);
            }

            jsonResponse(['entries' => $rows]);
        } else {
            jsonError('Unbekannte Aktion', 404);
        }
        break;

    // ── POST: Create calendar entry ─────────────────────────
    case 'POST':
        if ($action !== 'create') jsonError('Unbekannte Aktion', 404);

        $input = getJsonInput();
        $db = getDB();
        $uuid = $input['id'] ?? generateToken(8);

        if (empty($input['title']) || empty($input['entry_date'])) {
            jsonError('Titel und Datum sind Pflichtfelder');
        }

        $stmt = $db->prepare('
            INSERT INTO calendar_entries (
                user_id, uuid, title, description, entry_date, end_date,
                entry_time, entry_type, ort, kontakte, tags, erledigt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $uuid,
            $input['title'],
            $input['description'] ?? null,
            $input['entry_date'],
            $input['end_date'] ?? null,
            $input['entry_time'] ?? null,
            $input['entry_type'] ?? 'notiz',
            $input['ort'] ?? null,
            json_encode($input['kontakte'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($input['tags'] ?? [], JSON_UNESCAPED_UNICODE),
            $input['erledigt'] ?? 0
        ]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'uuid' => $uuid], 201);
        break;

    // ── PUT: Update calendar entry ──────────────────────────
    case 'PUT':
        $uuid = $_GET['uuid'] ?? '';
        if (empty($uuid)) jsonError('UUID fehlt');

        $input = getJsonInput();
        $db = getDB();

        $stmt = $db->prepare('
            UPDATE calendar_entries SET
                title = ?, description = ?, entry_date = ?, end_date = ?,
                entry_time = ?, entry_type = ?, ort = ?,
                kontakte = ?, tags = ?, erledigt = ?
            WHERE user_id = ? AND uuid = ?
        ');
        $stmt->execute([
            $input['title'] ?? '',
            $input['description'] ?? null,
            $input['entry_date'] ?? null,
            $input['end_date'] ?? null,
            $input['entry_time'] ?? null,
            $input['entry_type'] ?? 'notiz',
            $input['ort'] ?? null,
            json_encode($input['kontakte'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($input['tags'] ?? [], JSON_UNESCAPED_UNICODE),
            $input['erledigt'] ?? 0,
            $userId,
            $uuid
        ]);

        if ($stmt->rowCount() === 0) {
            jsonError('Eintrag nicht gefunden', 404);
        }

        jsonResponse(['success' => true]);
        break;

    // ── DELETE: Remove calendar entry ────────────────────────
    case 'DELETE':
        $uuid = $_GET['uuid'] ?? '';
        if (empty($uuid)) jsonError('UUID fehlt');

        $db = getDB();
        $stmt = $db->prepare('DELETE FROM calendar_entries WHERE user_id = ? AND uuid = ?');
        $stmt->execute([$userId, $uuid]);

        if ($stmt->rowCount() === 0) {
            jsonError('Eintrag nicht gefunden', 404);
        }

        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Methode nicht erlaubt', 405);
}
