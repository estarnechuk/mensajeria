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
    $Id_Llamada = $_POST['Id_Llamada'] ?? '';
    $Accion = $_POST['Accion'] ?? '';
    $Respuesta = trim($_POST['Respuesta'] ?? '');
    $Candidato = trim($_POST['Candidato'] ?? '');
    $DesdeCandidato = isset($_POST['DesdeCandidato']) && ctype_digit((string)$_POST['DesdeCandidato']) ? (int)$_POST['DesdeCandidato'] : 0;

    if ($Id_Llamada === '' || !ctype_digit((string)$Id_Llamada)) {
        throw new Exception('Llamada inválida');
    }

    $stmt = $CNX->prepare("SELECT * FROM mensajeria_llamadas WHERE Id = ?");
    $stmt->bind_param("i", $Id_Llamada);
    $stmt->execute();
    $llamada = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$llamada) {
        throw new Exception('La llamada no existe');
    }
    $EsOrigen = (string)$llamada['id_usuario_origen'] === (string)$Operador;
    $EsDestino = (string)$llamada['id_usuario_destino'] === (string)$Operador;
    if (!$EsOrigen && !$EsDestino) {
        http_response_code(403);
        throw new Exception('No participás de esta llamada');
    }

    // Transición de estado, si vino una Accion. Transiciones que no aplican
    // al estado actual se ignoran en silencio (los dos lados pollean y pueden
    // pisarse; no hace falta tratarlo como error).
    if ($Accion === 'aceptar' && $EsDestino && $llamada['Estado'] === 'sonando' && $Respuesta !== '') {
        $Fecha_Aceptada = date('d/m/Y');
        $Hora_Aceptada = date('H:i:s');
        $u = $CNX->prepare("UPDATE mensajeria_llamadas SET Estado = 'aceptada', Respuesta_SDP = ?, Fecha_Aceptada = ?, Hora_Aceptada = ? WHERE Id = ?");
        $u->bind_param("sssi", $Respuesta, $Fecha_Aceptada, $Hora_Aceptada, $Id_Llamada);
        $u->execute();
        $u->close();
        $llamada['Estado'] = 'aceptada';
        $llamada['Respuesta_SDP'] = $Respuesta;
        $llamada['Fecha_Aceptada'] = $Fecha_Aceptada;
        $llamada['Hora_Aceptada'] = $Hora_Aceptada;
    } elseif ($Accion === 'rechazar' && $EsDestino && $llamada['Estado'] === 'sonando') {
        $Fecha_Fin = date('d/m/Y');
        $Hora_Fin = date('H:i:s');
        $u = $CNX->prepare("UPDATE mensajeria_llamadas SET Estado = 'rechazada', Fecha_Fin = ?, Hora_Fin = ? WHERE Id = ?");
        $u->bind_param("ssi", $Fecha_Fin, $Hora_Fin, $Id_Llamada);
        $u->execute();
        $u->close();
        $llamada['Estado'] = 'rechazada';
    } elseif ($Accion === 'colgar' && $llamada['Estado'] === 'sonando') {
        $Fecha_Fin = date('d/m/Y');
        $Hora_Fin = date('H:i:s');
        $u = $CNX->prepare("UPDATE mensajeria_llamadas SET Estado = 'cancelada', Fecha_Fin = ?, Hora_Fin = ? WHERE Id = ?");
        $u->bind_param("ssi", $Fecha_Fin, $Hora_Fin, $Id_Llamada);
        $u->execute();
        $u->close();
        $llamada['Estado'] = 'cancelada';
    } elseif ($Accion === 'colgar' && $llamada['Estado'] === 'aceptada') {
        $Fecha_Fin = date('d/m/Y');
        $Hora_Fin = date('H:i:s');
        $Duracion = 0;
        if ($llamada['Fecha_Aceptada'] !== '' && $llamada['Hora_Aceptada'] !== '') {
            $inicioAceptada = DateTime::createFromFormat('d/m/Y H:i:s', $llamada['Fecha_Aceptada'] . ' ' . $llamada['Hora_Aceptada']);
            if ($inicioAceptada) {
                $Duracion = max(0, (new DateTime())->getTimestamp() - $inicioAceptada->getTimestamp());
            }
        }
        $u = $CNX->prepare("UPDATE mensajeria_llamadas SET Estado = 'finalizada', Fecha_Fin = ?, Hora_Fin = ?, Duracion_Segundos = ? WHERE Id = ?");
        $u->bind_param("ssii", $Fecha_Fin, $Hora_Fin, $Duracion, $Id_Llamada);
        $u->execute();
        $u->close();
        $llamada['Estado'] = 'finalizada';
        $llamada['Duracion_Segundos'] = $Duracion;
    }

    if ($Candidato !== '') {
        $c = $CNX->prepare("INSERT INTO mensajeria_llamadas_candidatos (Id_Llamada, id_usuario, Candidato) VALUES (?, ?, ?)");
        $c->bind_param("iis", $Id_Llamada, $Operador, $Candidato);
        $c->execute();
        $c->close();
    }

    $candidatosNuevos = [];
    $q = $CNX->prepare("SELECT Id, Candidato FROM mensajeria_llamadas_candidatos WHERE Id_Llamada = ? AND id_usuario != ? AND Id > ? ORDER BY Id ASC");
    $q->bind_param("iii", $Id_Llamada, $Operador, $DesdeCandidato);
    $q->execute();
    $rsCand = $q->get_result();
    while ($fc = $rsCand->fetch_assoc()) {
        $candidatosNuevos[] = ['Id' => (int)$fc['Id'], 'Candidato' => $fc['Candidato']];
    }
    $q->close();

    echo json_encode([
        'success' => true,
        'Estado' => $llamada['Estado'],
        'Respuesta_SDP' => $EsOrigen ? $llamada['Respuesta_SDP'] : '',
        'Duracion_Segundos' => (int)($llamada['Duracion_Segundos'] ?? 0),
        'Candidatos' => $candidatosNuevos
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
