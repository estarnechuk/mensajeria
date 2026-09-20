<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['Operador']) || ($_SESSION['Accesar'] ?? '') !== 'ADMINISTRADOR') {
    header("location: index.php");
    exit;
}
require_once(__DIR__ . '/csrf.php');
require_once(__DIR__ . '/Conexion.php');
mysqli_select_db($CNX, $database);

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($csrf_token)) {
        $mensaje = 'Token CSRF inválido, recargá la página e intentá de nuevo.';
    } else {
        $Id = (int)($_POST['Id'] ?? 0);
        $accion = $_POST['accion'] ?? '';
        if ($Id > 0 && $accion === 'aprobar') {
            $stmt = $CNX->prepare("UPDATE personal SET Aprobado = 1 WHERE Id = ? AND Aprobado = 0");
            $stmt->bind_param("i", $Id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Usuario aprobado.';
        } elseif ($Id > 0 && $accion === 'rechazar') {
            $stmt = $CNX->prepare("DELETE FROM personal WHERE Id = ? AND Aprobado = 0");
            $stmt->bind_param("i", $Id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Solicitud rechazada.';
        }
    }
}

$rsPendientes = $CNX->query("SELECT Id, DNI, Ap_Nom, Alias FROM personal WHERE Aprobado = 0 ORDER BY Id ASC");
$Pendientes = [];
while ($fila = $rsPendientes->fetch_assoc()) {
    $Pendientes[] = $fila;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Altas pendientes - Mensajería</title>
    <link href="/css/bootstrap5_css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/bootstrap_icons/bootstrap-icons.css">
    <style>
        body {
            background: #efeae2;
            font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif;
            min-height: 100vh;
        }
        .panel-header {
            background: #008069;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .panel-header a { color: #fff; }
        .panel-contenido {
            max-width: 900px;
            margin: 24px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="panel-header">
        <div><i class="bi bi-person-check-fill"></i> Altas pendientes de aprobación</div>
        <a href="index.php" title="Volver a Mensajería"><i class="bi bi-box-arrow-left"></i> Volver</a>
    </div>
    <div class="panel-contenido">
        <?php if ($mensaje): ?>
            <div class="alert alert-info py-2"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (empty($Pendientes)): ?>
            <p class="text-muted">No hay solicitudes pendientes.</p>
        <?php else: ?>
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Nombre completo</th>
                        <th>Alias</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($Pendientes as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['DNI'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['Ap_Nom'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['Alias'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end">
                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="Id" value="<?= (int)$p['Id'] ?>">
                                    <input type="hidden" name="accion" value="aprobar">
                                    <button type="submit" class="btn btn-sm btn-success" style="background:#008069;border-color:#008069;">Aprobar</button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Rechazar y borrar esta solicitud?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="Id" value="<?= (int)$p['Id'] ?>">
                                    <input type="hidden" name="accion" value="rechazar">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $CNX->close(); ?>
