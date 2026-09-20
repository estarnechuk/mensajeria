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
    $Contenido = trim($_POST['Contenido'] ?? '');
    $Asunto = $_POST['Asunto'] ?? '';
    $Respuesta_a = isset($_POST['Respuesta_a']) && $_POST['Respuesta_a'] !== '' ? (int)$_POST['Respuesta_a'] : 0;

    if ($Destino === '' || !ctype_digit((string)$Destino)) {
        throw new Exception('Destinatario inválido');
    }
    if ((string)$Destino === (string)$Operador) {
        throw new Exception('No podés enviarte un mensaje a vos mismo');
    }

    // Tipos de archivo permitidos: imágenes raster comunes, PDF y audio (notas de voz).
    // Se excluye SVG a propósito: es XML y puede llevar <script> embebido que se
    // ejecuta si el navegador lo abre directamente (no así dentro de un <img>).
    // 'video/webm' se acepta porque el contenedor WebM es el mismo para audio y video:
    // libmagic no siempre distingue un WebM solo-audio (el que graba MediaRecorder desde
    // el micrófono) de uno con video, y a veces lo reporta como "video/webm".
    $MIME_A_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
    ];
    $TAMANO_MAXIMO = 15 * 1024 * 1024; // 15MB

    $Adjunto_Archivo = '';
    $Adjunto_Nombre = '';
    $Adjunto_Tipo = '';

    if (isset($_FILES['Adjunto']) && $_FILES['Adjunto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $archivo = $_FILES['Adjunto'];
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo adjunto');
        }
        if ($archivo['size'] <= 0 || $archivo['size'] > $TAMANO_MAXIMO) {
            throw new Exception('El archivo supera el tamaño máximo permitido (15MB)');
        }

        // El tipo real se detecta por contenido (magic bytes), nunca por la extensión
        // del nombre ni por el Content-Type que declara el navegador (ambos falsificables).
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if (!isset($MIME_A_EXTENSION[$mimeReal])) {
            throw new Exception('Solo se permiten imágenes (JPG, PNG, GIF, WEBP, BMP), archivos PDF o notas de voz');
        }

        $extension = $MIME_A_EXTENSION[$mimeReal];
        $Adjunto_Archivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $Adjunto_Tipo = $mimeReal;
        $Adjunto_Nombre = str_replace(['"', "\r", "\n", '/', '\\'], '', basename($archivo['name']));

        $directorioAdjuntos = rtrim(require __DIR__ . '/adjuntos_config.php', '/\\');
        if (!is_dir($directorioAdjuntos) || !is_writable($directorioAdjuntos)) {
            throw new Exception('La carpeta de adjuntos no está disponible en el servidor');
        }
        $destino = $directorioAdjuntos . '/' . $Adjunto_Archivo;
        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new Exception('No se pudo guardar el archivo adjunto');
        }
    }

    if ($Contenido === '' && $Adjunto_Archivo === '') {
        throw new Exception('El mensaje no puede estar vacío');
    }

    // Verifica que el destinatario exista en personal
    $stmtChk = $CNX->prepare("SELECT DNI FROM personal WHERE DNI = ?");
    $stmtChk->bind_param("i", $Destino);
    $stmtChk->execute();
    $rsChk = $stmtChk->get_result();
    if ($rsChk->num_rows === 0) {
        $stmtChk->close();
        throw new Exception('El destinatario no existe');
    }
    $stmtChk->close();

    $Fecha_Envio = date('d/m/Y');
    $Hora_Envio = date('H:i:s');

    $stmt = $CNX->prepare("INSERT INTO mensajeria (id_usuario_origen, id_usuario_destino, id_externo, Asunto, Contenido, Adjunto_Archivo, Adjunto_Nombre, Adjunto_Tipo, Estado, Fecha_Envio, Hora_Envio, Fecha_Lectura, Hora_Lectura, Canal, Respuesta_a) VALUES (?, ?, '', ?, ?, ?, ?, ?, 0, ?, ?, '', '', 'Sistema', ?)");
    $stmt->bind_param("iisssssssi", $Operador, $Destino, $Asunto, $Contenido, $Adjunto_Archivo, $Adjunto_Nombre, $Adjunto_Tipo, $Fecha_Envio, $Hora_Envio, $Respuesta_a);
    $stmt->execute();
    $NuevoId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'Id' => $NuevoId,
        'Fecha_Envio' => $Fecha_Envio,
        'Hora_Envio' => $Hora_Envio
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
