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
    $Destino = $_POST['Destino'] ?? '';
    $Oferta = trim($_POST['Oferta'] ?? '');

    if ($Destino === '' || !ctype_digit((string)$Destino)) {
        throw new Exception('Destinatario inválido');
    }
    if ((string)$Destino === (string)$Operador) {
        throw new Exception('No podés llamarte a vos mismo');
    }
    if ($Oferta === '') {
        throw new Exception('Falta la oferta SDP');
    }

    $stmtChk = $CNX->prepare("SELECT DNI FROM personal WHERE DNI = ?");
    $stmtChk->bind_param("i", $Destino);
    $stmtChk->execute();
    $rsChk = $stmtChk->get_result();
    if ($rsChk->num_rows === 0) {
        $stmtChk->close();
        throw new Exception('El destinatario no existe');
    }
    $stmtChk->close();

    // Evita llamadas simultáneas: ni el que llama ni el destino pueden tener
    // ya una llamada en curso (sonando o aceptada) como origen o destino.
    $stmtActiva = $CNX->prepare("
        SELECT Id FROM mensajeria_llamadas
        WHERE Estado IN ('sonando', 'aceptada')
          AND (id_usuario_origen = ? OR id_usuario_destino = ? OR id_usuario_origen = ? OR id_usuario_destino = ?)
        LIMIT 1
    ");
    $stmtActiva->bind_param("iiii", $Operador, $Operador, $Destino, $Destino);
    $stmtActiva->execute();
    $rsActiva = $stmtActiva->get_result();
    if ($rsActiva->num_rows > 0) {
        $stmtActiva->close();
        throw new Exception('Ya hay una llamada en curso');
    }
    $stmtActiva->close();

    $Fecha_Inicio = date('d/m/Y');
    $Hora_Inicio = date('H:i:s');

    $stmt = $CNX->prepare("INSERT INTO mensajeria_llamadas (id_usuario_origen, id_usuario_destino, Estado, Oferta_SDP, Fecha_Inicio, Hora_Inicio) VALUES (?, ?, 'sonando', ?, ?, ?)");
    $stmt->bind_param("iisss", $Operador, $Destino, $Oferta, $Fecha_Inicio, $Hora_Inicio);
    $stmt->execute();
    $NuevoId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'Id_Llamada' => $NuevoId
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
