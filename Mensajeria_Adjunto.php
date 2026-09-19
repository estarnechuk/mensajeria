<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['Accesar']) || !in_array($_SESSION['Accesar'], ['ADMINISTRADOR', 'OPERADOR', 'CONTROL2'])) {
    header("location: ../login/index.php");
    exit;
}
require_once('../Conexion.php');
mysqli_select_db($CNX, $database);

$Operador = $_SESSION['Operador'];
$Id = $_GET['Id'] ?? '';

if ($Id === '' || !ctype_digit((string)$Id)) {
    http_response_code(400);
    exit('Solicitud inválida');
}

$stmt = $CNX->prepare("SELECT id_usuario_origen, id_usuario_destino, Adjunto_Archivo, Adjunto_Nombre, Adjunto_Tipo FROM mensajeria WHERE Id = ?");
$stmt->bind_param("i", $Id);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (isset($CNX)) { $CNX->close(); }

if (!$fila || $fila['Adjunto_Archivo'] === '') {
    http_response_code(404);
    exit('Adjunto no encontrado');
}

// Solo el emisor o el receptor de ese mensaje puede ver el adjunto
if ((string)$fila['id_usuario_origen'] !== (string)$Operador && (string)$fila['id_usuario_destino'] !== (string)$Operador) {
    http_response_code(403);
    exit('No autorizado');
}

$directorioAdjuntos = rtrim(require __DIR__ . '/adjuntos_config.php', '/\\');
$ruta = $directorioAdjuntos . '/' . $fila['Adjunto_Archivo'];
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$nombreDescarga = str_replace(['"', "\r", "\n"], '', $fila['Adjunto_Nombre'] !== '' ? $fila['Adjunto_Nombre'] : $fila['Adjunto_Archivo']);

header('Content-Type: ' . $fila['Adjunto_Tipo']);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $nombreDescarga . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($ruta);
exit;
