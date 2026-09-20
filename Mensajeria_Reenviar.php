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
    $Ids = $_POST['Ids'] ?? [];

    if ($Destino === '' || !ctype_digit((string)$Destino)) {
        throw new Exception('Destinatario inválido');
    }
    if ((string)$Destino === (string)$Operador) {
        throw new Exception('No podés reenviarte un mensaje a vos mismo');
    }
    if (!is_array($Ids) || count($Ids) === 0) {
        throw new Exception('No se seleccionó ningún mensaje para reenviar');
    }
    foreach ($Ids as $id) {
        if (!ctype_digit((string)$id)) {
            throw new Exception('Selección de mensajes inválida');
        }
    }

    // Verifica que el destinatario exista en personal
    $stmtChk = $CNX->prepare("SELECT DNI FROM personal WHERE DNI = ?");
    $stmtChk->bind_param("i", $Destino);
    $stmtChk->execute();
    if ($stmtChk->get_result()->num_rows === 0) {
        $stmtChk->close();
        throw new Exception('El destinatario no existe');
    }
    $stmtChk->close();

    // Solo se pueden reenviar mensajes de conversaciones donde el usuario logueado
    // participó (como origen o destino) — evita reenviar adivinando Ids ajenos.
    $marcadores = implode(',', array_fill(0, count($Ids), '?'));
    $tipos = str_repeat('i', count($Ids));
    $stmtSel = $CNX->prepare("
        SELECT Id, Asunto, Contenido, Adjunto_Archivo, Adjunto_Nombre, Adjunto_Tipo
        FROM mensajeria
        WHERE Id IN ($marcadores) AND (id_usuario_origen = ? OR id_usuario_destino = ?)
        ORDER BY Id ASC
    ");
    $parametros = array_merge($Ids, [$Operador, $Operador]);
    $stmtSel->bind_param($tipos . 'ii', ...$parametros);
    $stmtSel->execute();
    $mensajesAReenviar = $stmtSel->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSel->close();

    if (count($mensajesAReenviar) === 0) {
        throw new Exception('No se encontraron mensajes válidos para reenviar');
    }

    $Fecha_Envio = date('d/m/Y');
    $Hora_Envio = date('H:i:s');
    $stmtIns = $CNX->prepare("
        INSERT INTO mensajeria (id_usuario_origen, id_usuario_destino, id_externo, Asunto, Contenido, Adjunto_Archivo, Adjunto_Nombre, Adjunto_Tipo, Estado, Fecha_Envio, Hora_Envio, Fecha_Lectura, Hora_Lectura, Canal, Respuesta_a)
        VALUES (?, ?, '', ?, ?, ?, ?, ?, 0, ?, ?, '', '', 'Sistema', 0)
    ");
    $reenviados = 0;
    foreach ($mensajesAReenviar as $m) {
        $stmtIns->bind_param(
            "iisssssss",
            $Operador,
            $Destino,
            $m['Asunto'],
            $m['Contenido'],
            $m['Adjunto_Archivo'],
            $m['Adjunto_Nombre'],
            $m['Adjunto_Tipo'],
            $Fecha_Envio,
            $Hora_Envio
        );
        $stmtIns->execute();
        $reenviados++;
    }
    $stmtIns->close();

    echo json_encode(['success' => true, 'reenviados' => $reenviados]);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
if (isset($CNX)) { $CNX->close(); }
