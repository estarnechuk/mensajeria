<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['Accesar']) || !in_array($_SESSION['Accesar'], ['ADMINISTRADOR', 'OPERADOR', 'CONTROL2'])) {
    header("location: ../login/index.php");
    exit;
}
require_once('../csrf.php');
require_once('../Conexion.php');
$csrf_token = $_POST['csrf_token'] ?? '';
if (!csrf_verify($csrf_token)) {
    die("Token CSRF inválido");
}
mysqli_select_db($CNX, $database);

header('Content-Type: application/json');

try {
    $Operador = $_SESSION['Operador'];

    // Agrupa la mensajería por el otro participante de la conversación (par de usuarios)
    $stmt = $CNX->prepare("
        SELECT
            CASE WHEN id_usuario_origen = ? THEN id_usuario_destino ELSE id_usuario_origen END AS Contacto,
            MAX(Id) AS UltimoId,
            SUM(CASE WHEN id_usuario_destino = ? AND Estado = 0 THEN 1 ELSE 0 END) AS NoLeidos
        FROM mensajeria
        WHERE id_usuario_origen = ? OR id_usuario_destino = ?
        GROUP BY Contacto
        ORDER BY UltimoId DESC
    ");
    $stmt->bind_param("iiii", $Operador, $Operador, $Operador, $Operador);
    $stmt->execute();
    $rs = $stmt->get_result();

    $stmtAlias = $CNX->prepare("SELECT Alias FROM personal WHERE DNI = ?");
    $stmtMsg = $CNX->prepare("SELECT Contenido, Adjunto_Archivo, Adjunto_Tipo, Fecha_Envio, Hora_Envio, id_usuario_origen FROM mensajeria WHERE Id = ?");

    $conversaciones = [];
    while ($fila = $rs->fetch_assoc()) {
        $Contacto = $fila['Contacto'];

        $stmtAlias->bind_param("i", $Contacto);
        $stmtAlias->execute();
        $rowAlias = $stmtAlias->get_result()->fetch_assoc();

        $stmtMsg->bind_param("i", $fila['UltimoId']);
        $stmtMsg->execute();
        $rowMsg = $stmtMsg->get_result()->fetch_assoc();

        $ultimoMensaje = $rowMsg['Contenido'] ?? '';
        if ($ultimoMensaje === '' && !empty($rowMsg['Adjunto_Archivo'])) {
            if (strpos($rowMsg['Adjunto_Tipo'], 'image/') === 0) {
                $ultimoMensaje = '📷 Foto';
            } elseif (strpos($rowMsg['Adjunto_Tipo'], 'audio/') === 0 || $rowMsg['Adjunto_Tipo'] === 'video/webm') {
                $ultimoMensaje = '🎤 Nota de voz';
            } else {
                $ultimoMensaje = '📄 Documento PDF';
            }
        }

        $conversaciones[] = [
            'Contacto' => (int)$Contacto,
            'Alias' => $rowAlias['Alias'] ?? '',
            'UltimoMensaje' => $ultimoMensaje,
            'Fecha_Envio' => $rowMsg['Fecha_Envio'] ?? '',
            'Hora_Envio' => $rowMsg['Hora_Envio'] ?? '',
            'EsMio' => isset($rowMsg['id_usuario_origen']) && (string)$rowMsg['id_usuario_origen'] === (string)$Operador,
            'NoLeidos' => (int)$fila['NoLeidos']
        ];
    }
    $stmtAlias->close();
    $stmtMsg->close();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'conversaciones' => $conversaciones
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
