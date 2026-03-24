<?php
// ============================================================
//  rsvp.php  ·  API pública de invitación y confirmación
//  GET  ?t=<token>        → devuelve datos del invitado
//  POST (JSON)            → guarda la confirmación
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS: solo tu propio dominio (ajusta si usas subdominio)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = 'https://sergioykaren.com';
if ($origin === $allowed) {
    header("Access-Control-Allow-Origin: $allowed");
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET: devuelve datos del token ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['t'] ?? '');

    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        json_out(['ok' => false, 'error' => 'Token inválido.'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT para, pases, respondido FROM invitaciones WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        json_out(['ok' => false, 'error' => 'Invitación no encontrada.'], 404);
    }

    json_out([
        'ok'         => true,
        'para'       => $row['para'],
        'pases'      => (int)$row['pases'],
        'respondido' => (bool)$row['respondido'],
    ]);
}

// ── POST: guarda confirmación ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        json_out(['ok' => false, 'error' => 'Cuerpo inválido.'], 400);
    }

    $token        = trim($body['token']        ?? '');
    $nombre       = trim($body['nombre']       ?? '');
    $asistira     = isset($body['asistira'])   ? (bool)$body['asistira'] : null;
    $acompanantes = (int)($body['acompanantes'] ?? 0);
    $restriccion  = trim($body['restriccion']  ?? '');
    $mensaje      = trim($body['mensaje']      ?? '');

    // Validaciones básicas
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        json_out(['ok' => false, 'error' => 'Token inválido.'], 400);
    }
    if ($nombre === '') {
        json_out(['ok' => false, 'error' => 'Nombre requerido.'], 422);
    }
    if ($asistira === null) {
        json_out(['ok' => false, 'error' => 'Indica si asistirás.'], 422);
    }

    $db = getDB();

    // Verificar que el token existe y no ha sido respondido
    $stmt = $db->prepare('SELECT pases, respondido FROM invitaciones WHERE token = ?');
    $stmt->execute([$token]);
    $inv = $stmt->fetch();

    if (!$inv) {
        json_out(['ok' => false, 'error' => 'Invitación no encontrada.'], 404);
    }
    if ($inv['respondido']) {
        json_out(['ok' => false, 'error' => 'Esta invitación ya fue confirmada.'], 409);
    }

    // Validar que acompañantes no supere pases asignados
    $maxAcomp = (int)$inv['pases'] - 1; // el propio invitado ocupa 1 pase
    if ($acompanantes < 0 || $acompanantes > $maxAcomp) {
        json_out(['ok' => false, 'error' => 'Número de acompañantes fuera de rango.'], 422);
    }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? null;

    // Guardar confirmación
    $ins = $db->prepare('
        INSERT INTO confirmaciones
            (token, nombre, asistira, acompanantes, restriccion, mensaje, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $ins->execute([
        $token,
        $nombre,
        (int)$asistira,
        $acompanantes,
        $restriccion ?: null,
        $mensaje     ?: null,
        $ip,
    ]);

    // Marcar invitación como respondida
    $db->prepare('UPDATE invitaciones SET respondido = 1, respondido_en = NOW() WHERE token = ?')
       ->execute([$token]);

    json_out(['ok' => true, 'message' => '¡Confirmación guardada!']);
}

json_out(['ok' => false, 'error' => 'Método no permitido.'], 405);