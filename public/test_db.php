<?php
// ============================================================
//  test_db.php  ·  Prueba de conexión MySQL
//  ⚠️  ELIMINA este archivo del servidor una vez que confirmes
//      que la conexión funciona. No lo dejes en producción.
// ============================================================

// ── Pon tus datos reales aquí ────────────────────────────────
$host    = 'srv942.hstgr.io';
$db      = 'u548874252_boda';
$user    = 'u548874252_admin';
$pass    = '^iimGco!Z0I';
$charset = 'utf8mb4';
// ─────────────────────────────────────────────────────────────

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$ok      = false;
$mensaje = '';
$tablas  = [];
$version = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $tablas  = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $ok      = true;
    $mensaje = '¡Conexión exitosa!';

} catch (PDOException $e) {
    $mensaje = 'Error: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Test DB</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:ui-sans-serif,system-ui,Arial;background:#f5f5f5;display:grid;place-items:center;min-height:100vh;padding:24px}
    .card{background:#fff;border-radius:14px;box-shadow:0 8px 30px #0000001a;padding:28px;max-width:520px;width:100%}
    h1{font-size:18px;margin-bottom:18px;color:#333}
    .badge{display:inline-block;padding:8px 16px;border-radius:8px;font-weight:700;font-size:15px;margin-bottom:18px}
    .ok  {background:#d4edda;color:#155724}
    .err {background:#f8d7da;color:#721c24}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{text-align:left;padding:7px 10px;font-size:13px;border-bottom:1px solid #eee}
    th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#888}
    .warn{margin-top:18px;padding:10px 14px;background:#fff3cd;border-radius:8px;font-size:12px;color:#856404}
  </style>
</head>
<body>
  <div class="card">
    <h1>🔌 Prueba de conexión MySQL</h1>

    <span class="badge <?= $ok ? 'ok' : 'err' ?>">
      <?= htmlspecialchars($mensaje) ?>
    </span>

    <?php if ($ok): ?>
      <table>
        <tr><th>Parámetro</th><th>Valor</th></tr>
        <tr><td>Host</td><td><?= htmlspecialchars($host) ?></td></tr>
        <tr><td>Base de datos</td><td><?= htmlspecialchars($db) ?></td></tr>
        <tr><td>Usuario</td><td><?= htmlspecialchars($user) ?></td></tr>
        <tr><td>Versión MySQL</td><td><?= htmlspecialchars($version) ?></td></tr>
      </table>

      <table style="margin-top:18px">
        <tr><th>Tablas encontradas (<?= count($tablas) ?>)</th></tr>
        <?php if ($tablas): ?>
          <?php foreach ($tablas as $t): ?>
            <tr><td><?= htmlspecialchars($t) ?></td></tr>
          <?php endforeach ?>
        <?php else: ?>
          <tr><td style="color:#888;font-style:italic">Sin tablas aún — ejecuta schema.sql</td></tr>
        <?php endif ?>
      </table>
    <?php endif ?>

    <div class="warn">
      ⚠️ <strong>Elimina este archivo</strong> del servidor en cuanto termines la prueba.
      No lo dejes en producción.
    </div>
  </div>
</body>
</html>