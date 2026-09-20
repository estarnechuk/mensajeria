<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['Operador'])) {
    header("location: login/index.php");
    exit;
}
require_once(__DIR__ . '/csrf.php');
require_once(__DIR__ . '/Conexion.php');
$csrf_token = $_POST['csrf_token'] ?? '';
if (!csrf_verify($csrf_token)) {
    die("Token CSRF inválido");
}
mysqli_select_db($CNX, $database);

header('Content-Type: application/json');

try {
    $Operador = $_SESSION['Operador'];
    $Contacto = $_POST['Contacto'] ?? '';

    if ($Contacto === '' || !ctype_digit((string)$Contacto)) {
        throw new Exception('Contacto inválido');
    }

    $Fecha_Lectura = date('d/m/Y');
    $Hora_Lectura = date('H:i:s');

    // Marca como leídos todos los mensajes pendientes que ese contacto le envió al usuario logueado
    $stmt = $CNX->prepare("
        UPDATE mensajeria
        SET Estado = 1, Fecha_Lectura = ?, Hora_Lectura = ?
        WHERE id_usuario_origen = ? AND id_usuario_destino = ? AND Estado = 0
    ");
    $stmt->bind_param("ssii", $Fecha_Lectura, $Hora_Lectura, $Contacto, $Operador);
    $stmt->execute();
    $Afectados = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'marcados' => $Afectados
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
if (isset($CNX)) { $CNX->close(); }
