<?php
// ============================================================
// Stakeholder Map v1.2 – Stakeholders CRUD API
// ============================================================
require_once __DIR__ . '/config.php';

$user = requireAuth();
$userId = $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($method) {

    // ── GET: List all stakeholders ───────────────────────────
    case 'GET':
        if ($action === 'list') {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM stakeholders WHERE user_id = ? ORDER BY updated_at DESC');
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();

            // Parse JSON fields
            foreach ($rows as &$row) {
                $row['tags'] = json_decode($row['tags'] ?? '[]', true) ?: [];
                $row['verbindungen'] = json_decode($row['verbindungen'] ?? '[]', true) ?: [];
                unset($row['user_id']);
            }

            jsonResponse(['stakeholders' => $rows]);

        } elseif ($action === 'activity') {
            $db = getDB();
            $stmt = $db->prepare('SELECT month_key, added, gepflegt FROM activity_log WHERE user_id = ? ORDER BY month_key DESC LIMIT 12');
            $stmt->execute([$userId]);
            jsonResponse(['activity' => $stmt->fetchAll()]);

        } else {
            jsonError('Unbekannte Aktion', 404);
        }
        break;

    // ── POST: Create or Sync stakeholders ────────────────────
    case 'POST':
        $input = getJsonInput();
        $db = getDB();

        // Bulk sync (for localStorage migration)
        if ($action === 'sync') {
            $stakeholders = $input['stakeholders'] ?? [];
            $activity = $input['activity'] ?? [];
            $synced = 0;

            $db->beginTransaction();
            try {
                foreach ($stakeholders as $s) {
                    $uuid = $s['id'] ?? generateToken(8);

                    // Check if exists
                    $check = $db->prepare('SELECT id FROM stakeholders WHERE user_id = ? AND uuid = ?');
                    $check->execute([$userId, $uuid]);

                    if ($check->fetch()) {
                        // Update existing
                        $stmt = $db->prepare('
                            UPDATE stakeholders SET
                                name = ?, firma = ?, rolle = ?, email = ?, telefon = ?,
                                linkedin = ?, tags = ?, beziehungsstaerke = ?, prioritaet = ?,
                                wohnort = ?, wohnort_lat = ?, wohnort_lng = ?,
                                arbeitsort = ?, arbeitsort_lat = ?, arbeitsort_lng = ?,
                                notizen = ?, geburtstag = ?, letzter_kontakt = ?, naechster_schritt = ?,
                                verbindungen = ?
                            WHERE user_id = ? AND uuid = ?
                        ');
                    } else {
                        // Insert new
                        $stmt = $db->prepare('
                            INSERT INTO stakeholders (
                                name, firma, rolle, email, telefon,
                                linkedin, tags, beziehungsstaerke, prioritaet,
                                wohnort, wohnort_lat, wohnort_lng,
                                arbeitsort, arbeitsort_lat, arbeitsort_lng,
                                notizen, geburtstag, letzter_kontakt, naechster_schritt,
                                verbindungen, user_id, uuid
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ');
                    }

                    $params = [
                        $s['name'] ?? '',
                        $s['firma'] ?? null,
                        $s['rolle'] ?? null,
                        $s['email'] ?? null,
                        $s['telefon'] ?? null,
                        $s['linkedin'] ?? null,
                        json_encode($s['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                        $s['beziehungsstaerke'] ?? 3,
                        $s['prioritaet'] ?? 'mittel',
                        $s['wohnort'] ?? null,
                        $s['wohnort_lat'] ?? null,
                        $s['wohnort_lng'] ?? null,
                        $s['arbeitsort'] ?? null,
                        $s['arbeitsort_lat'] ?? null,
                        $s['arbeitsort_lng'] ?? null,
                        $s['notizen'] ?? null,
                        $s['geburtstag'] ?? null,
                        !empty($s['letzter_kontakt']) ? $s['letzter_kontakt'] : null,
                        $s['naechster_schritt'] ?? null,
                        json_encode($s['verbindungen'] ?? [], JSON_UNESCAPED_UNICODE),
                        $userId,
                        $uuid
                    ];

                    $stmt->execute($params);
                    $synced++;
                }

                // Sync activity
                foreach ($activity as $monthKey => $data) {
                    $stmt = $db->prepare('
                        INSERT INTO activity_log (user_id, month_key, added, gepflegt)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE added = VALUES(added), gepflegt = VALUES(gepflegt)
                    ');
                    $stmt->execute([
                        $userId,
                        $monthKey,
                        $data['added'] ?? 0,
                        $data['gepflegt'] ?? 0
                    ]);
                }

                $db->commit();
                jsonResponse(['success' => true, 'synced' => $synced]);

            } catch (Exception $e) {
                $db->rollBack();
                jsonError('Sync fehlgeschlagen: ' . $e->getMessage(), 500);
            }
        }

        // Single create
        if ($action === 'create') {
            $s = $input;
            $uuid = $s['id'] ?? generateToken(8);

            $stmt = $db->prepare('
                INSERT INTO stakeholders (
                    user_id, uuid, name, firma, rolle, email, telefon,
                    linkedin, tags, beziehungsstaerke, prioritaet,
                    wohnort, wohnort_lat, wohnort_lng,
                    arbeitsort, arbeitsort_lat, arbeitsort_lng,
                    notizen, geburtstag, letzter_kontakt, naechster_schritt, verbindungen
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId, $uuid,
                $s['name'] ?? '',
                $s['firma'] ?? null,
                $s['rolle'] ?? null,
                $s['email'] ?? null,
                $s['telefon'] ?? null,
                $s['linkedin'] ?? null,
                json_encode($s['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                $s['beziehungsstaerke'] ?? 3,
                $s['prioritaet'] ?? 'mittel',
                $s['wohnort'] ?? null,
                $s['wohnort_lat'] ?? null,
                $s['wohnort_lng'] ?? null,
                $s['arbeitsort'] ?? null,
                $s['arbeitsort_lat'] ?? null,
                $s['arbeitsort_lng'] ?? null,
                $s['notizen'] ?? null,
                $s['geburtstag'] ?? null,
                !empty($s['letzter_kontakt']) ? $s['letzter_kontakt'] : null,
                $s['naechster_schritt'] ?? null,
                json_encode($s['verbindungen'] ?? [], JSON_UNESCAPED_UNICODE)
            ]);

            jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'uuid' => $uuid], 201);
        }

        jsonError('Unbekannte Aktion', 404);
        break;

    // ── PUT: Update stakeholder ──────────────────────────────
    case 'PUT':
        $uuid = $_GET['uuid'] ?? '';
        if (empty($uuid)) jsonError('UUID fehlt');

        $input = getJsonInput();
        $db = getDB();

        $stmt = $db->prepare('
            UPDATE stakeholders SET
                name = ?, firma = ?, rolle = ?, email = ?, telefon = ?,
                linkedin = ?, tags = ?, beziehungsstaerke = ?, prioritaet = ?,
                wohnort = ?, wohnort_lat = ?, wohnort_lng = ?,
                arbeitsort = ?, arbeitsort_lat = ?, arbeitsort_lng = ?,
                notizen = ?, geburtstag = ?, letzter_kontakt = ?, naechster_schritt = ?,
                verbindungen = ?
            WHERE user_id = ? AND uuid = ?
        ');
        $stmt->execute([
            $input['name'] ?? '',
            $input['firma'] ?? null,
            $input['rolle'] ?? null,
            $input['email'] ?? null,
            $input['telefon'] ?? null,
            $input['linkedin'] ?? null,
            json_encode($input['tags'] ?? [], JSON_UNESCAPED_UNICODE),
            $input['beziehungsstaerke'] ?? 3,
            $input['prioritaet'] ?? 'mittel',
            $input['wohnort'] ?? null,
            $input['wohnort_lat'] ?? null,
            $input['wohnort_lng'] ?? null,
            $input['arbeitsort'] ?? null,
            $input['arbeitsort_lat'] ?? null,
            $input['arbeitsort_lng'] ?? null,
            $input['notizen'] ?? null,
            $input['geburtstag'] ?? null,
            !empty($input['letzter_kontakt']) ? $input['letzter_kontakt'] : null,
            $input['naechster_schritt'] ?? null,
            json_encode($input['verbindungen'] ?? [], JSON_UNESCAPED_UNICODE),
            $userId,
            $uuid
        ]);

        if ($stmt->rowCount() === 0) {
            jsonError('Kontakt nicht gefunden', 404);
        }

        jsonResponse(['success' => true]);
        break;

    // ── DELETE: Remove stakeholder ───────────────────────────
    case 'DELETE':
        $uuid = $_GET['uuid'] ?? '';
        if (empty($uuid)) jsonError('UUID fehlt');

        $db = getDB();
        $stmt = $db->prepare('DELETE FROM stakeholders WHERE user_id = ? AND uuid = ?');
        $stmt->execute([$userId, $uuid]);

        if ($stmt->rowCount() === 0) {
            jsonError('Kontakt nicht gefunden', 404);
        }

        jsonResponse(['success' => true]);
        break;

    default:
        jsonError('Methode nicht erlaubt', 405);
}
