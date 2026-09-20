<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../csrf.php');

if (isset($_SESSION['Operador'])) {
    header("location: ../index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($csrf_token)) {
        $error = 'Token CSRF inválido, recargá la página e intentá de nuevo.';
    } else {
        $DNI = $_POST['DNI'] ?? '';
        $Password = $_POST['Password'] ?? '';

        if ($DNI === '' || !ctype_digit((string)$DNI) || $Password === '') {
            $error = 'Ingresá tu DNI y contraseña.';
        } else {
            require_once(__DIR__ . '/../Conexion.php');
            mysqli_select_db($CNX, $database);

            $stmt = $CNX->prepare("SELECT DNI, Alias, Password, Aprobado, Tipo_Personal FROM personal WHERE DNI = ?");
            $stmt->bind_param("i", $DNI);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($fila && password_verify($Password, $fila['Password'])) {
                if ((int)$fila['Aprobado'] !== 1) {
                    $error = 'Tu cuenta está pendiente de aprobación por un administrador.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['Operador'] = (int)$fila['DNI'];
                    $_SESSION['Alias'] = $fila['Alias'];
                    $_SESSION['Accesar'] = $fila['Tipo_Personal'];
                    header("location: ../index.php");
                    exit;
                }
            } else {
                $error = 'DNI o contraseña incorrectos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingresar - Mensajería</title>
    <link rel="stylesheet" href="/css/bootstrap5_css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/bootstrap_icons/bootstrap-icons.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #efeae2;
            font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 360px;
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
        }
        .login-card h1 {
            font-size: 1.25rem;
            color: #008069;
            margin-bottom: 1.25rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Mensajería Alma Sistemas</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">DNI</label>
                <input type="text" inputmode="numeric" name="DNI" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" name="Password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100" style="background:#008069;border-color:#008069;">Ingresar</button>
        </form>
        <div class="text-center mt-3">
            <a href="registro.php">¿No tenés cuenta? Registrate</a>
        </div>
    </div>
</body>
</html>
