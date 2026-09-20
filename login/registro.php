<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../csrf.php');
require_once(__DIR__ . '/../password_rules.php');

if (isset($_SESSION['Operador'])) {
    header("location: ../index.php");
    exit;
}

$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($csrf_token)) {
        $error = 'Token CSRF inválido, recargá la página e intentá de nuevo.';
    } else {
        $DNI = trim($_POST['DNI'] ?? '');
        $Ap_Nom = trim($_POST['Ap_Nom'] ?? '');
        $Alias = trim($_POST['Alias'] ?? '');
        $Password = $_POST['Password'] ?? '';
        $Password2 = $_POST['Password2'] ?? '';

        if (!preg_match('/^\d{6,9}$/', $DNI)) {
            $error = 'Ingresá un DNI válido (solo números, entre 6 y 9 dígitos).';
        } elseif ($Ap_Nom === '' || $Alias === '') {
            $error = 'Completá tu nombre y un alias.';
        } elseif ($Password !== $Password2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $erroresPassword = validar_password($Password);
            if (!empty($erroresPassword)) {
                $error = implode(' ', $erroresPassword);
            } else {
                require_once(__DIR__ . '/../Conexion.php');
                mysqli_select_db($CNX, $database);

                $stmt = $CNX->prepare("SELECT Id FROM personal WHERE DNI = ?");
                $stmt->bind_param("i", $DNI);
                $stmt->execute();
                $existe = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existe) {
                    $error = 'Ese DNI ya está registrado.';
                } else {
                    $hash = password_hash($Password, PASSWORD_DEFAULT);
                    $stmt = $CNX->prepare("INSERT INTO personal (DNI, Ap_Nom, Alias, Tipo_Personal, Aprobado, Password) VALUES (?, ?, ?, 'USUARIO', 0, ?)");
                    $stmt->bind_param("isss", $DNI, $Ap_Nom, $Alias, $hash);
                    $stmt->execute();
                    $stmt->close();
                    $exito = true;
                }
                $CNX->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrarse - Mensajería</title>
    <link rel="stylesheet" href="/css/bootstrap5_css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/bootstrap_icons/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #efeae2;
            font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif;
            padding: 2rem 0;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
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
        .form-text-ayuda {
            font-size: 12px;
            color: #667781;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Crear cuenta - Mensajería</h1>
        <?php if ($exito): ?>
            <div class="alert alert-success py-2">
                Tu solicitud fue enviada. Un administrador debe aprobarla antes de que puedas ingresar.
            </div>
            <a href="index.php" class="btn btn-success w-100" style="background:#008069;border-color:#008069;">Volver al inicio</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">DNI</label>
                    <input type="text" inputmode="numeric" name="DNI" class="form-control" value="<?= htmlspecialchars($_POST['DNI'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Apellido y Nombres</label>
                    <input type="text" name="Ap_Nom" class="form-control" value="<?= htmlspecialchars($_POST['Ap_Nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Alias que te identificará como usuario</label>
                    <input type="text" name="Alias" class="form-control" value="<?= htmlspecialchars($_POST['Alias'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-1">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="Password" class="form-control" required>
                </div>
                <div class="mb-2 form-text-ayuda">
                    Mínimo 8 caracteres, al menos una mayúscula, un número, un carácter especial, y sin números correlativos (ej. 123 o 321).
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="Password2" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100" style="background:#008069;border-color:#008069;">Registrarme</button>
            </form>
            <div class="text-center mt-3">
                <a href="index.php">Ya tengo cuenta, ingresar</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
