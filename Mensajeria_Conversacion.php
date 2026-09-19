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
    $Contacto = $_POST['Contacto'] ?? '';

    if ($Contacto === '' || !ctype_digit((string)$Contacto)) {
        throw new Exception('Contacto inválido');
    }

    $stmt = $CNX->prepare("
        SELECT Id, id_usuario_origen, id_usuario_destino, Asunto, Contenido, Adjunto_Archivo, Adjunto_Nombre, Adjunto_Tipo, Estado, Fecha_Envio, Hora_Envio, Fecha_Lectura, Hora_Lectura, Respuesta_a
        FROM mensajeria
        WHERE (id_usuario_origen = ? AND id_usuario_destino = ?) OR (id_usuario_origen = ? AND id_usuario_destino = ?)
        ORDER BY Id ASC
    ");
    $stmt->bind_param("iiii", $Operador, $Contacto, $Contacto, $Operador);
    $stmt->execute();
    $rs = $stmt->get_result();

    $mensajes = [];
    while ($fila = $rs->fetch_assoc()) {
        $mensajes[] = [
            'Id' => (int)$fila['Id'],
            'Asunto' => $fila['Asunto'],
            'Contenido' => $fila['Contenido'],
            'Estado' => (int)$fila['Estado'],
            'Fecha_Envio' => $fila['Fecha_Envio'],
            'Hora_Envio' => $fila['Hora_Envio'],
            'Fecha_Lectura' => $fila['Fecha_Lectura'],
            'Hora_Lectura' => $fila['Hora_Lectura'],
            'Respuesta_a' => (int)$fila['Respuesta_a'],
            'EsMio' => (string)$fila['id_usuario_origen'] === (string)$Operador,
            'TieneAdjunto' => $fila['Adjunto_Archivo'] !== '',
            'Adjunto_Nombre' => $fila['Adjunto_Nombre'],
            'Adjunto_EsImagen' => strpos($fila['Adjunto_Tipo'], 'image/') === 0,
            // 'video/webm' se trata como audio: es el mismo caso de detección ambigua
            // de libmagic descrito en Mensajeria_Enviar.php para notas de voz grabadas.
            'Adjunto_EsAudio' => strpos($fila['Adjunto_Tipo'], 'audio/') === 0 || $fila['Adjunto_Tipo'] === 'video/webm'
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'mensajes' => $mensajes
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
