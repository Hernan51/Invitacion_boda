<?php
// ============================================================
//  admin_api.php  ·  API privada del panel de administración
//
//  POST /admin_api.php?action=generar   → crea token + invitación
//  GET  /admin_api.php?action=listar    → lista todas las inv.
//  GET  /admin_api.php?action=export    → CSV con confirmaciones
//
//  Autenticación: header  X-Admin-Key: <clave>
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../db.php';

// ── Clave de API para el admin (cámbiala, larga y aleatoria) ─
define('ADMIN_KEY', 'KarenSergio2026_boda_clave_XkQ9mPz7wR3nBv');

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar autenticación
$key = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
if (!hash_equals(ADMIN_KEY, $key)) {
    json_out(['ok' => false, 'error' => 'No autorizado.'], 401);
}

$action = $_GET['action'] ?? '';

// ── GENERAR token e invitación ─────────────────────────────
if ($action === 'generar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $para      = trim($body['para']      ?? '');
    $pases     = (int)($body['pases']    ?? 0);
    $interno   = trim($body['id']        ?? '');
    $usuario   = trim($body['user']      ?? '');

    if ($para === '' || $pases < 1 || $pases > 10) {
        json_out(['ok' => false, 'error' => 'Datos inválidos.'], 422);
    }

    // Generar token seguro de 32 hex chars
    $token = bin2hex(random_bytes(16));

    $db = getDB();
    $stmt = $db->prepare('
        INSERT INTO invitaciones (token, para, pases, id_interno, creado_por)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $token,
        $para,
        $pases,
        $interno ?: null,
        $usuario ?: null,
    ]);

    json_out([
        'ok'    => true,
        'token' => $token,
    ]);
}

// ── LISTAR invitaciones con estado ────────────────────────
if ($action === 'listar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = getDB();
    $rows = $db->query('
        SELECT
            i.token,
            i.para,
            i.pases,
            i.id_interno,
            i.creado_por,
            i.creado_en,
            i.respondido,
            i.respondido_en,
            c.nombre        AS conf_nombre,
            c.asistira      AS conf_asistira,
            c.acompanantes  AS conf_acompanantes,
            c.restriccion   AS conf_restriccion,
            c.mensaje       AS conf_mensaje
        FROM invitaciones i
        LEFT JOIN confirmaciones c ON c.token = i.token
        ORDER BY i.creado_en DESC
    ')->fetchAll();

    json_out(['ok' => true, 'data' => $rows]);
}

// ── EXPORT CSV ────────────────────────────────────────────
if ($action === 'export' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = getDB();
    $rows = $db->query('
        SELECT
            i.creado_en, i.para, i.pases, i.id_interno, i.creado_por,
            i.respondido, i.respondido_en,
            c.nombre, c.asistira, c.acompanantes, c.restriccion, c.mensaje
        FROM invitaciones i
        LEFT JOIN confirmaciones c ON c.token = i.token
        ORDER BY i.creado_en DESC
    ')->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="invitaciones_' . date('Ymd') . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel
    fputcsv($out, [
        'Creado', 'Para', 'Pases', 'ID interno', 'Creado por',
        'Respondido', 'Respondido en',
        'Nombre confirmación', '¿Asistirá?', 'Acompañantes', 'Restricción', 'Mensaje'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['creado_en'],
            $r['para'],
            $r['pases'],
            $r['id_interno'],
            $r['creado_por'],
            $r['respondido'] ? 'Sí' : 'No',
            $r['respondido_en'],
            $r['nombre'],
            $r['asistira'] === null ? '' : ($r['asistira'] ? 'Sí' : 'No'),
            $r['acompanantes'],
            $r['restriccion'],
            $r['mensaje'],
        ]);
    }
    fclose($out);
    exit;
}

json_out(['ok' => false, 'error' => 'Acción no válida.'], 400);
