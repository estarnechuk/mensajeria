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

    $stmt = $CNX->prepare("
        SELECT Id, id_usuario_origen, id_usuario_destino, Estado, Oferta_SDP
        FROM mensajeria_llamadas
        WHERE Estado IN ('sonando', 'aceptada')
          AND (id_usuario_origen = ? OR id_usuario_destino = ?)
        ORDER BY Id DESC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $Operador, $Operador);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        echo json_encode(['success' => true, 'llamada' => null]);
        exit;
    }

    $EsOrigen = (string)$fila['id_usuario_origen'] === (string)$Operador;
    $Contacto = $EsOrigen ? $fila['id_usuario_destino'] : $fila['id_usuario_origen'];

    $stmtAlias = $CNX->prepare("SELECT Alias FROM personal WHERE DNI = ?");
    $stmtAlias->bind_param("i", $Contacto);
    $stmtAlias->execute();
    $rowAlias = $stmtAlias->get_result()->fetch_assoc();
    $stmtAlias->close();

    echo json_encode([
        'success' => true,
        'llamada' => [
            'Id' => (int)$fila['Id'],
            'Rol' => $EsOrigen ? 'origen' : 'destino',
            'Contacto' => (int)$Contacto,
            'Alias' => $rowAlias['Alias'] ?? '',
            'Estado' => $fila['Estado'],
            'Oferta_SDP' => $EsOrigen ? '' : $fila['Oferta_SDP']
        ]
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
if (isset($CNX)) { $CNX->close(); }
