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
mysqli_select_db($CNX, $database);

$Operador = $_SESSION['Operador'];
$MiAlias = $_SESSION['Alias'] ?? '';

$stmtContactos = $CNX->prepare("SELECT DNI, Alias FROM personal WHERE DNI != ? AND Aprobado = 1 ORDER BY Alias ASC");
$stmtContactos->bind_param("i", $Operador);
$stmtContactos->execute();
$rsContactos = $stmtContactos->get_result();
$Contactos = [];
while ($fila = $rsContactos->fetch_assoc()) {
    $Contactos[] = ['DNI' => (int)$fila['DNI'], 'Alias' => $fila['Alias']];
}
$stmtContactos->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensajería Alma Sistemas</title>
    <link rel="stylesheet" href="/css/bootstrap5_css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/bootstrap_icons/bootstrap-icons.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            background: #efeae2;
            font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif;
        }
        .wa-app {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.15);
            background: #fff;
        }
        /* ---- Sidebar ---- */
        .wa-sidebar {
            width: 360px;
            min-width: 280px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e9edef;
            background: #fff;
        }
        .wa-sidebar-header {
            background: #008069;
            color: #fff;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .wa-sidebar-header .titulo {
            font-weight: 600;
            font-size: 17px;
        }
        .wa-sidebar-header .mi-alias {
            font-size: 12px;
            opacity: 0.85;
        }
        .wa-search {
            padding: 8px 12px;
            border-bottom: 1px solid #e9edef;
        }
        .wa-search input {
            background: #f0f2f5;
            border: none;
            border-radius: 20px;
            padding: 8px 14px;
            width: 100%;
            font-size: 14px;
        }
        .wa-search input:focus {
            outline: none;
            box-shadow: 0 0 0 2px #b6ded4;
        }
        .wa-contactos {
            flex: 1;
            overflow-y: auto;
        }
        .wa-contacto {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f0f2f5;
        }
        .wa-contacto:hover {
            background: #f5f6f6;
        }
        .wa-contacto.activo {
            background: #e9edef;
        }
        .wa-avatar {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 16px;
            margin-right: 12px;
        }
        .wa-contacto-info {
            flex: 1;
            min-width: 0;
        }
        .wa-contacto-linea1 {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .wa-contacto-alias {
            font-weight: 500;
            font-size: 15px;
            color: #111b21;
        }
        .wa-contacto-hora {
            font-size: 11px;
            color: #667781;
            white-space: nowrap;
        }
        .wa-contacto-linea2 {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .wa-contacto-preview {
            font-size: 13px;
            color: #667781;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .wa-badge {
            background: #25d366;
            color: #fff;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            min-width: 18px;
            text-align: center;
        }
        /* ---- Panel principal ---- */
        .wa-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .wa-vacio {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #667781;
            background: #f7f8fa;
        }
        .wa-vacio i {
            font-size: 64px;
            margin-bottom: 12px;
            opacity: 0.6;
        }
        .wa-chat-header {
            background: #f0f2f5;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e9edef;
        }
        .wa-chat-header .wa-contacto-alias {
            font-size: 15px;
        }
        .wa-mensajes {
            flex: 1;
            overflow-y: auto;
            padding: 20px 6%;
            background: #efeae2;
        }
        .wa-burbuja-fila {
            display: flex;
            margin-bottom: 8px;
        }
        .wa-burbuja-fila.mia {
            justify-content: flex-end;
        }
        .wa-burbuja {
            max-width: 65%;
            padding: 6px 9px 8px 9px;
            border-radius: 8px;
            box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
            position: relative;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .wa-burbuja.mia {
            background: #d9fdd3;
        }
        .wa-burbuja.otro {
            background: #fff;
        }
        .wa-burbuja .asunto {
            font-weight: 700;
            font-size: 13px;
            display: block;
            margin-bottom: 2px;
        }
        .wa-burbuja .contenido {
            font-size: 14px;
            color: #111b21;
        }
        .wa-burbuja .hora {
            font-size: 10px;
            color: #667781;
            text-align: right;
            margin-top: 2px;
            margin-left: 8px;
            float: right;
        }
        .wa-input-row {
            background: #f0f2f5;
            padding: 10px 16px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            position: relative;
        }
        .wa-emoji-picker {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 16px;
            margin-bottom: 8px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.25);
            width: 300px;
            max-height: 260px;
            overflow-y: auto;
            padding: 8px;
            z-index: 50;
        }
        .wa-emoji-picker.abierto {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }
        .wa-emoji-picker button {
            background: transparent;
            border: none;
            font-size: 22px;
            width: 36px;
            height: 36px;
            line-height: 1;
            border-radius: 6px;
            cursor: pointer;
        }
        .wa-emoji-picker button:hover {
            background: #f0f2f5;
        }
        .wa-btn-adjuntar {
            background: transparent;
            border: none;
            color: #54656f;
            font-size: 22px;
            width: 42px;
            height: 42px;
            min-width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wa-btn-adjuntar:hover {
            color: #008069;
        }
        .wa-preview-adjunto {
            background: #f0f2f5;
            border-top: 1px solid #e9edef;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .wa-preview-adjunto img {
            max-height: 60px;
            border-radius: 6px;
            display: block;
        }
        .wa-preview-pdf {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #111b21;
        }
        .wa-preview-pdf i {
            font-size: 22px;
            color: #d32f2f;
        }
        .wa-preview-adjunto audio {
            height: 36px;
            max-width: 240px;
        }
        #quitarAdjunto {
            background: transparent;
            border: none;
            color: #667781;
            font-size: 16px;
        }
        .wa-adjunto-imagen {
            max-width: 260px;
            max-height: 260px;
            border-radius: 6px;
            display: block;
            cursor: pointer;
            margin-bottom: 4px;
        }
        .wa-adjunto-pdf {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.04);
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 4px;
            text-decoration: none;
            color: #111b21;
        }
        .wa-adjunto-pdf i {
            font-size: 26px;
            color: #d32f2f;
        }
        .wa-adjunto-pdf span {
            font-size: 13px;
            word-break: break-all;
        }
        .wa-adjunto-audio {
            display: block;
            margin-bottom: 4px;
            max-width: 260px;
            height: 40px;
        }
        .wa-input-row-grabando {
            display: none;
        }
        .wa-grabando-indicador {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #111b21;
            font-size: 14px;
            padding: 0 4px;
        }
        .wa-grabando-punto {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #d32f2f;
            flex-shrink: 0;
            animation: wa-grabando-parpadeo 1s infinite;
        }
        @keyframes wa-grabando-parpadeo {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.25; }
        }
        .wa-tilde {
            color: #8696a0;
            margin-left: 3px;
            font-size: 14px;
            vertical-align: -1px;
        }
        .wa-tilde.leido {
            color: #53bdeb;
        }
        .wa-input-row textarea {
            flex: 1;
            border: none;
            border-radius: 20px;
            padding: 9px 16px;
            resize: none;
            font-size: 14px;
            max-height: 100px;
        }
        .wa-input-row textarea:focus {
            outline: none;
        }
        .wa-btn-enviar {
            background: #008069;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            min-width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wa-btn-enviar:hover {
            background: #006a58;
        }
        .wa-sin-mensajes {
            text-align: center;
            color: #667781;
            font-size: 13px;
            margin-top: 30px;
        }
        .wa-btn-header {
            background: transparent;
            border: none;
            color: #54656f;
            font-size: 20px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .wa-btn-header:hover {
            background: rgba(0,0,0,0.06);
        }
        .wa-btn-header:disabled {
            opacity: 0.4;
        }
        #chatHeaderSeleccion {
            background: #e9edef;
        }
        .wa-check-seleccion {
            display: none;
            align-items: center;
            margin: 0 6px;
            color: #667781;
            font-size: 20px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .wa-check-seleccion.marcado {
            color: #008069;
        }
        .modo-seleccion .wa-check-seleccion {
            display: flex;
        }
        .wa-burbuja-fila.mia .wa-check-seleccion {
            order: 2;
        }
        .modo-seleccion .wa-burbuja-fila {
            cursor: pointer;
        }
        .wa-burbuja-fila.seleccionada .wa-burbuja {
            outline: 2px solid #008069;
        }
        .wa-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        .wa-modal {
            background: #fff;
            border-radius: 10px;
            width: 360px;
            max-width: 92vw;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .wa-modal-header {
            background: #008069;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
        }
        .wa-modal-header button {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 18px;
        }
        .wa-modal .wa-contactos {
            max-height: 340px;
        }
    </style>
</head>
<body>
    <div class="wa-app">
        <div class="wa-sidebar">
            <div class="wa-sidebar-header">
                <div>
                    <div class="titulo"><i class="bi bi-chat-dots-fill"></i> Mensajería Alma Sistemas</div>
                    <div class="mi-alias"><?php echo htmlspecialchars($MiAlias, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div>
                    <?php if (($_SESSION['Accesar'] ?? '') === 'ADMINISTRADOR'): ?>
                        <a href="admin_altas.php" title="Altas pendientes" style="color:#fff; margin-right:12px;"><i class="bi bi-person-check-fill"></i></a>
                    <?php endif; ?>
                    <a href="login/logout.php" title="Cerrar sesión" style="color:#fff;"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
            <div class="wa-search">
                <input type="text" id="buscarContacto" placeholder="Buscar o empezar chat nuevo">
            </div>
            <div class="wa-contactos" id="listaContactos">
                <!-- Se renderiza por JS -->
            </div>
        </div>
        <div class="wa-main">
            <div class="wa-vacio" id="chatVacio">
                <i class="bi bi-chat-square-text"></i>
                <div>Seleccioná una conversación para comenzar</div>
            </div>
            <div id="chatActivo" style="display:none; flex-direction:column; flex:1; min-height:0;">
                <div class="wa-chat-header" id="chatHeaderNormal">
                    <div class="wa-avatar" id="chatAvatar"></div>
                    <div class="wa-contacto-alias" id="chatAlias"></div>
                    <div style="margin-left:auto;">
                        <button type="button" class="wa-btn-header" id="btnModoSeleccion" title="Seleccionar mensajes">
                            <i class="bi bi-check2-square"></i>
                        </button>
                    </div>
                </div>
                <div class="wa-chat-header" id="chatHeaderSeleccion" style="display:none;">
                    <button type="button" class="wa-btn-header" id="btnCancelarSeleccion" title="Cancelar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <div class="wa-contacto-alias" id="contadorSeleccion" style="margin-left:10px;">0 seleccionados</div>
                    <div style="margin-left:auto;">
                        <button type="button" class="wa-btn-header" id="btnReenviarSeleccion" title="Reenviar" disabled>
                            <i class="bi bi-reply-fill" style="transform:scaleX(-1);"></i>
                        </button>
                    </div>
                </div>
                <div class="wa-mensajes" id="mensajesContainer"></div>
                <div class="wa-preview-adjunto" id="previewAdjunto" style="display:none;">
                    <div id="previewAdjuntoContenido"></div>
                    <button type="button" id="quitarAdjunto" title="Quitar adjunto"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="wa-input-row" id="inputRowNormal">
                    <div class="wa-emoji-picker" id="emojiPicker"></div>
                    <button class="wa-btn-adjuntar" id="btnEmoji" title="Insertar emoji"><i class="bi bi-emoji-smile"></i></button>
                    <button class="wa-btn-adjuntar" id="btnAdjuntar" title="Adjuntar imagen o PDF"><i class="bi bi-paperclip"></i></button>
                    <input type="file" id="inputAdjunto" accept="image/*,application/pdf" style="display:none;">
                    <textarea id="cajaTexto" rows="1" placeholder="Escribí un mensaje"></textarea>
                    <button class="wa-btn-adjuntar" id="btnGrabar" title="Grabar nota de voz"><i class="bi bi-mic-fill"></i></button>
                    <button class="wa-btn-enviar" id="btnEnviar" title="Enviar"><i class="bi bi-send-fill"></i></button>
                </div>
                <div class="wa-input-row wa-input-row-grabando" id="inputRowGrabando">
                    <button class="wa-btn-adjuntar" id="btnCancelarGrabacion" title="Cancelar grabación"><i class="bi bi-trash-fill" style="color:#d32f2f;"></i></button>
                    <div class="wa-grabando-indicador"><span class="wa-grabando-punto"></span> Grabando <span id="grabandoTiempo">0:00</span></div>
                    <button class="wa-btn-enviar" id="btnDetenerGrabacion" title="Detener y previsualizar"><i class="bi bi-check-lg"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalReenviar" class="wa-modal-overlay" style="display:none;">
        <div class="wa-modal">
            <div class="wa-modal-header">
                <span>Reenviar a...</span>
                <button type="button" id="cerrarModalReenviar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="wa-search">
                <input type="text" id="buscarReenviar" placeholder="Buscar contacto">
            </div>
            <div class="wa-contactos" id="listaReenviarContactos"></div>
        </div>
    </div>

    <script src="/js/jquery-3.4.1.min.js"></script>
    <script>
        const CSRF_TOKEN = '<?php echo csrf_token(); ?>';
        const HOY = '<?php echo date('d/m/Y'); ?>';
        const CONTACTOS = <?php echo json_encode($Contactos); ?>;
        const COLORES_AVATAR = ['#f56a6a', '#f5a623', '#7ac142', '#00a884', '#4a90d9', '#9b59b6', '#e91e8c'];
        const EMOJIS = [
            '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩',
            '😘', '😋', '😛', '🤪', '🤨', '🧐', '🤓', '😎', '🥳', '😏', '😒', '😞', '😔', '🙁', '😣', '😖',
            '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥',
            '🤔', '🤗', '🤭', '🤫', '😴', '🤤', '😷', '🤒', '🤕', '🤢', '🥴', '😵', '🤠',
            '👍', '👎', '👏', '🙌', '🙏', '🤝', '👋', '💪', '✌️', '🤞', '🤙', '👌',
            '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '💕', '💖',
            '📦', '🚚', '🛵', '📍', '📞', '✅', '❌', '⚠️', '⏰', '🕒', '📝', '📅', '💬', '🔥', '⭐', '💯', '🎉', '👀'
        ];

        let contactoActivo = null;
        let aliasActivo = '';
        let ultimaFirmaConversacion = '';
        let conversaciones = {}; // DNI -> resumen de bandeja
        let archivoSeleccionado = null;
        const TAMANO_MAXIMO_ADJUNTO = 15 * 1024 * 1024; // 15MB, debe coincidir con Mensajeria_Enviar.php

        let mediaRecorder = null;
        let audioChunksGrabacion = [];
        let grabacionTimer = null;
        let grabacionSegundos = 0;
        let grabacionCancelada = false;

        let ultimosMensajes = [];
        let modoSeleccion = false;
        let mensajesSeleccionados = new Set();

        const sonidoNuevoMensaje = new Audio('/Sonidos/Aviso_de_nuevo_mensaje.mp3');
        let totalNoLeidosAnterior = -1; // -1 = todavía no cargamos la bandeja ninguna vez

        function escapeHtml(texto) {
            return $('<div>').text(texto == null ? '' : texto).html();
        }

        function colorAvatar(dni) {
            return COLORES_AVATAR[dni % COLORES_AVATAR.length];
        }

        function iniciales(alias) {
            return (alias || '?').trim().charAt(0).toUpperCase();
        }

        function cargarBandeja() {
            $.post('Mensajeria_Bandeja.php', { csrf_token: CSRF_TOKEN }, function (resp) {
                if (!resp || !resp.success) return;
                conversaciones = {};
                let totalNoLeidos = 0;
                resp.conversaciones.forEach(function (c) {
                    conversaciones[c.Contacto] = c;
                    totalNoLeidos += c.NoLeidos || 0;
                });
                // Suena solo cuando el total de no leídos SUBE (mensaje entrante nuevo),
                // nunca en la primera carga ni cuando baja (al marcar como leído).
                if (totalNoLeidosAnterior !== -1 && totalNoLeidos > totalNoLeidosAnterior) {
                    sonidoNuevoMensaje.currentTime = 0;
                    sonidoNuevoMensaje.play().catch(function () {});
                }
                totalNoLeidosAnterior = totalNoLeidos;
                renderSidebar();
            }, 'json');
        }

        function renderSidebar() {
            const filtro = ($('#buscarContacto').val() || '').toLowerCase();
            const conContacto = CONTACTOS.map(function (c) {
                return Object.assign({ Contacto: c.DNI, Alias: c.Alias, UltimoMensaje: '', Fecha_Envio: '', Hora_Envio: '', NoLeidos: 0, _orden: 0 }, conversaciones[c.DNI] || {}, { Alias: c.Alias });
            });
            conContacto.forEach(function (c) {
                c._orden = conversaciones[c.Contacto] ? 1 : 0;
            });
            conContacto.sort(function (a, b) {
                if (a._orden !== b._orden) return b._orden - a._orden;
                if (a._orden === 1) return (b.Fecha_Envio + b.Hora_Envio).localeCompare(a.Fecha_Envio + a.Hora_Envio);
                return a.Alias.localeCompare(b.Alias);
            });

            const $lista = $('#listaContactos').empty();
            conContacto.filter(function (c) {
                return !filtro || c.Alias.toLowerCase().indexOf(filtro) !== -1;
            }).forEach(function (c) {
                const activo = contactoActivo === c.Contacto ? ' activo' : '';
                const horaMostrar = c.Fecha_Envio ? (c.Fecha_Envio === HOY ? c.Hora_Envio.substring(0, 5) : c.Fecha_Envio) : '';
                const previewPrefijo = c.EsMio ? 'Vos: ' : '';
                const badge = c.NoLeidos > 0 ? '<span class="wa-badge">' + c.NoLeidos + '</span>' : '';
                const $fila = $(
                    '<div class="wa-contacto' + activo + '" data-dni="' + c.Contacto + '">' +
                        '<div class="wa-avatar" style="background:' + colorAvatar(c.Contacto) + '">' + escapeHtml(iniciales(c.Alias)) + '</div>' +
                        '<div class="wa-contacto-info">' +
                            '<div class="wa-contacto-linea1">' +
                                '<span class="wa-contacto-alias">' + escapeHtml(c.Alias) + '</span>' +
                                '<span class="wa-contacto-hora">' + escapeHtml(horaMostrar) + '</span>' +
                            '</div>' +
                            '<div class="wa-contacto-linea2">' +
                                '<span class="wa-contacto-preview">' + escapeHtml(previewPrefijo + (c.UltimoMensaje || '')) + '</span>' +
                                badge +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
                $fila.on('click', function () { abrirConversacion(c.Contacto, c.Alias); });
                $lista.append($fila);
            });
        }

        function abrirConversacion(dni, alias) {
            contactoActivo = dni;
            aliasActivo = alias;
            ultimaFirmaConversacion = '';
            modoSeleccion = false;
            mensajesSeleccionados.clear();
            $('#chatHeaderSeleccion').hide();
            $('#chatHeaderNormal').css('display', 'flex');
            $('#chatVacio').hide();
            $('#chatActivo').css('display', 'flex');
            $('#chatAvatar').css('background', colorAvatar(dni)).text(iniciales(alias));
            $('#chatAlias').text(alias);
            renderSidebar();
            cargarConversacion(true);
            if ((conversaciones[dni] || {}).NoLeidos > 0) {
                marcarLeido(dni);
            }
            if (mediaRecorder) detenerGrabacion(true);
            quitarAdjuntoSeleccionado();
            cerrarEmojiPicker();
            $('#cajaTexto').trigger('focus');
        }

        function cargarConversacion(forzarScroll) {
            if (!contactoActivo) return;
            $.post('Mensajeria_Conversacion.php', { Contacto: contactoActivo, csrf_token: CSRF_TOKEN }, function (resp) {
                if (!resp || !resp.success) return;
                const firma = resp.mensajes.map(function (m) { return m.Id; }).join(',');
                if (firma === ultimaFirmaConversacion) return;
                ultimaFirmaConversacion = firma;
                ultimosMensajes = resp.mensajes;
                mensajesSeleccionados.clear();
                renderMensajes(forzarScroll);
            }, 'json');
        }

        function renderMensajes(forzarScroll) {
            const $cont = $('#mensajesContainer').empty().toggleClass('modo-seleccion', modoSeleccion);
            if (ultimosMensajes.length === 0) {
                $cont.append('<div class="wa-sin-mensajes">Todavía no hay mensajes. ¡Escribí el primero!</div>');
            } else {
                ultimosMensajes.forEach(function (m) {
                    const lado = m.EsMio ? 'mia' : 'otro';
                    const hora = (m.Hora_Envio || '').substring(0, 5);
                    const asunto = m.Asunto ? '<span class="asunto">' + escapeHtml(m.Asunto) + '</span>' : '';
                    const contenido = m.Contenido ? '<span class="contenido">' + escapeHtml(m.Contenido) + '</span>' : '';
                    let adjunto = '';
                    if (m.TieneAdjunto) {
                        const url = 'Mensajeria_Adjunto.php?Id=' + m.Id;
                        if (m.Adjunto_EsImagen) {
                            adjunto = '<img class="wa-adjunto-imagen" src="' + url + '" onclick="window.open(\'' + url + '\', \'_blank\')">';
                        } else if (m.Adjunto_EsAudio) {
                            adjunto = '<audio class="wa-adjunto-audio" controls src="' + url + '"></audio>';
                        } else {
                            adjunto = '<a class="wa-adjunto-pdf" href="' + url + '" target="_blank">' +
                                '<i class="bi bi-file-earmark-pdf-fill"></i>' +
                                '<span>' + escapeHtml(m.Adjunto_Nombre || 'documento.pdf') + '</span>' +
                            '</a>';
                        }
                    }
                    let tilde = '';
                    let tituloHora = '';
                    if (m.EsMio) {
                        tituloHora = m.Estado === 1
                            ? 'Leído a las ' + (m.Hora_Lectura || '').substring(0, 5) + (m.Fecha_Lectura && m.Fecha_Lectura !== m.Fecha_Envio ? ' (' + m.Fecha_Lectura + ')' : '')
                            : 'No leído todavía';
                        tilde = ' <i class="bi bi-check wa-tilde' + (m.Estado === 1 ? ' leido' : '') + '"></i>';
                    }
                    const marcado = mensajesSeleccionados.has(m.Id) ? ' marcado' : '';
                    const seleccionada = mensajesSeleccionados.has(m.Id) ? ' seleccionada' : '';
                    const $fila = $(
                        '<div class="wa-burbuja-fila ' + lado + seleccionada + '" data-id="' + m.Id + '">' +
                            '<div class="wa-check-seleccion' + marcado + '"><i class="bi ' + (mensajesSeleccionados.has(m.Id) ? 'bi-check-circle-fill' : 'bi-circle') + '"></i></div>' +
                            '<div class="wa-burbuja ' + lado + '">' +
                                asunto +
                                adjunto +
                                contenido +
                                '<span class="hora" title="' + escapeHtml(tituloHora) + '">' + escapeHtml(hora) + tilde + '</span>' +
                            '</div>' +
                        '</div>'
                    );
                    $fila.on('click', function (e) {
                        if (!modoSeleccion) return;
                        e.preventDefault();
                        e.stopPropagation();
                        toggleSeleccionMensaje(m.Id);
                    });
                    $cont.append($fila);
                });
            }
            if (forzarScroll || true) {
                $cont.scrollTop($cont[0].scrollHeight);
            }
        }

        function activarModoSeleccion(idInicial) {
            modoSeleccion = true;
            mensajesSeleccionados.clear();
            if (idInicial) mensajesSeleccionados.add(idInicial);
            $('#chatHeaderNormal').hide();
            $('#chatHeaderSeleccion').css('display', 'flex');
            renderMensajes(false);
            actualizarContadorSeleccion();
        }

        function cancelarModoSeleccion() {
            modoSeleccion = false;
            mensajesSeleccionados.clear();
            $('#chatHeaderSeleccion').hide();
            $('#chatHeaderNormal').css('display', 'flex');
            renderMensajes(false);
        }

        function toggleSeleccionMensaje(id) {
            if (mensajesSeleccionados.has(id)) {
                mensajesSeleccionados.delete(id);
            } else {
                mensajesSeleccionados.add(id);
            }
            renderMensajes(false);
            actualizarContadorSeleccion();
        }

        function actualizarContadorSeleccion() {
            const n = mensajesSeleccionados.size;
            $('#contadorSeleccion').text(n + (n === 1 ? ' seleccionado' : ' seleccionados'));
            $('#btnReenviarSeleccion').prop('disabled', n === 0);
        }

        function abrirModalReenviar() {
            if (mensajesSeleccionados.size === 0) return;
            $('#buscarReenviar').val('');
            renderListaReenviar();
            $('#modalReenviar').css('display', 'flex');
            $('#buscarReenviar').trigger('focus');
        }

        function cerrarModalReenviar() {
            $('#modalReenviar').hide();
        }

        function renderListaReenviar() {
            const filtro = ($('#buscarReenviar').val() || '').toLowerCase();
            const $lista = $('#listaReenviarContactos').empty();
            CONTACTOS.filter(function (c) {
                return !filtro || c.Alias.toLowerCase().indexOf(filtro) !== -1;
            }).forEach(function (c) {
                const $fila = $(
                    '<div class="wa-contacto" data-dni="' + c.DNI + '">' +
                        '<div class="wa-avatar" style="background:' + colorAvatar(c.DNI) + '">' + escapeHtml(iniciales(c.Alias)) + '</div>' +
                        '<div class="wa-contacto-info"><span class="wa-contacto-alias">' + escapeHtml(c.Alias) + '</span></div>' +
                    '</div>'
                );
                $fila.on('click', function () { confirmarReenvio(c.DNI); });
                $lista.append($fila);
            });
        }

        function confirmarReenvio(dni) {
            const ids = Array.from(mensajesSeleccionados);
            $.post('Mensajeria_Reenviar.php', { Destino: dni, Ids: ids, csrf_token: CSRF_TOKEN }, function (resp) {
                if (resp && resp.success) {
                    cerrarModalReenviar();
                    cancelarModoSeleccion();
                    cargarBandeja();
                    if (contactoActivo === dni) {
                        ultimaFirmaConversacion = '';
                        cargarConversacion(true);
                    }
                } else {
                    alert((resp && resp.error) || 'No se pudo reenviar el mensaje');
                }
            }, 'json').fail(function () {
                alert('No se pudo reenviar el mensaje');
            });
        }

        function marcarLeido(dni) {
            $.post('Mensajeria_MarcarLeido.php', { Contacto: dni, csrf_token: CSRF_TOKEN }, function () {
                cargarBandeja();
            }, 'json');
        }

        function renderPreviewAdjunto() {
            const $preview = $('#previewAdjunto');
            const $contenido = $('#previewAdjuntoContenido').empty();
            if (!archivoSeleccionado) {
                $preview.hide();
                return;
            }
            if (archivoSeleccionado.type.indexOf('image/') === 0) {
                const url = URL.createObjectURL(archivoSeleccionado);
                $contenido.append($('<img>').attr('src', url));
            } else if (archivoSeleccionado.type.indexOf('audio/') === 0 || archivoSeleccionado.type === 'video/webm') {
                const url = URL.createObjectURL(archivoSeleccionado);
                $contenido.append($('<audio controls>').attr('src', url));
            } else {
                $contenido.append(
                    '<div class="wa-preview-pdf"><i class="bi bi-file-earmark-pdf-fill"></i><span>' + escapeHtml(archivoSeleccionado.name) + '</span></div>'
                );
            }
            $preview.css('display', 'flex');
        }

        function quitarAdjuntoSeleccionado() {
            archivoSeleccionado = null;
            $('#inputAdjunto').val('');
            renderPreviewAdjunto();
        }

        function renderEmojiPicker() {
            const $panel = $('#emojiPicker');
            EMOJIS.forEach(function (emoji) {
                $('<button type="button"></button>').text(emoji).on('click', function () {
                    insertarEmoji(emoji);
                }).appendTo($panel);
            });
        }

        function insertarEmoji(emoji) {
            const campo = document.getElementById('cajaTexto');
            const inicio = campo.selectionStart ?? campo.value.length;
            const fin = campo.selectionEnd ?? campo.value.length;
            campo.value = campo.value.slice(0, inicio) + emoji + campo.value.slice(fin);
            const nuevaPosicion = inicio + emoji.length;
            campo.setSelectionRange(nuevaPosicion, nuevaPosicion);
            campo.focus();
        }

        function toggleEmojiPicker() {
            $('#emojiPicker').toggleClass('abierto');
        }

        function cerrarEmojiPicker() {
            $('#emojiPicker').removeClass('abierto');
        }

        function formatoTiempoGrabacion(segundos) {
            const m = Math.floor(segundos / 60);
            const s = segundos % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function iniciarGrabacion() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
                alert('Este navegador no permite grabar notas de voz');
                return;
            }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                audioChunksGrabacion = [];
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) audioChunksGrabacion.push(e.data);
                };
                mediaRecorder.onstop = function () {
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    if (!grabacionCancelada && audioChunksGrabacion.length > 0) {
                        const tipo = mediaRecorder.mimeType || 'audio/webm';
                        let extension = 'webm';
                        if (tipo.indexOf('ogg') !== -1) extension = 'ogg';
                        else if (tipo.indexOf('mp4') !== -1) extension = 'm4a';
                        else if (tipo.indexOf('wav') !== -1) extension = 'wav';
                        const blob = new Blob(audioChunksGrabacion, { type: tipo });
                        archivoSeleccionado = new File([blob], 'nota_voz.' + extension, { type: tipo });
                        renderPreviewAdjunto();
                    }
                    audioChunksGrabacion = [];
                    grabacionCancelada = false;
                    mediaRecorder = null;
                };
                mediaRecorder.start();
                grabacionSegundos = 0;
                $('#grabandoTiempo').text('0:00');
                $('#inputRowNormal').hide();
                $('#inputRowGrabando').css('display', 'flex');
                grabacionTimer = setInterval(function () {
                    grabacionSegundos++;
                    $('#grabandoTiempo').text(formatoTiempoGrabacion(grabacionSegundos));
                }, 1000);
            }).catch(function () {
                alert('No se pudo acceder al micrófono. Verificá los permisos del navegador.');
            });
        }

        function detenerGrabacion(cancelar) {
            clearInterval(grabacionTimer);
            grabacionTimer = null;
            $('#inputRowGrabando').hide();
            $('#inputRowNormal').css('display', 'flex');
            if (mediaRecorder) {
                grabacionCancelada = cancelar;
                if (mediaRecorder.state !== 'inactive') mediaRecorder.stop();
            }
        }

        function enviarMensaje() {
            const texto = $('#cajaTexto').val().trim();
            if ((!texto && !archivoSeleccionado) || !contactoActivo) return;

            const formData = new FormData();
            formData.append('Destino', contactoActivo);
            formData.append('Contenido', texto);
            formData.append('csrf_token', CSRF_TOKEN);
            if (archivoSeleccionado) {
                formData.append('Adjunto', archivoSeleccionado);
            }

            $('#cajaTexto').val('');
            quitarAdjuntoSeleccionado();

            $.ajax({
                url: 'Mensajeria_Enviar.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (resp) {
                if (resp && resp.success) {
                    ultimaFirmaConversacion = '';
                    cargarConversacion(true);
                    cargarBandeja();
                } else {
                    alert((resp && resp.error) || 'No se pudo enviar el mensaje');
                }
            }).fail(function () {
                alert('No se pudo enviar el mensaje');
            });
        }

        $(document).ready(function () {
            cargarBandeja();
            setInterval(cargarBandeja, 6000);
            setInterval(function () { if (contactoActivo) cargarConversacion(false); }, 4000);

            $('#buscarContacto').on('input', renderSidebar);
            $('#btnEnviar').on('click', enviarMensaje);
            $('#cajaTexto').on('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    enviarMensaje();
                }
            });

            $('#btnAdjuntar').on('click', function () { $('#inputAdjunto').trigger('click'); });
            $('#inputAdjunto').on('change', function () {
                const archivo = this.files[0];
                if (!archivo) return;
                const esImagen = archivo.type.indexOf('image/') === 0;
                const esPdf = archivo.type === 'application/pdf';
                if (!esImagen && !esPdf) {
                    alert('Solo se pueden adjuntar imágenes o archivos PDF');
                    $(this).val('');
                    return;
                }
                if (archivo.size > TAMANO_MAXIMO_ADJUNTO) {
                    alert('El archivo supera el tamaño máximo permitido (15MB)');
                    $(this).val('');
                    return;
                }
                archivoSeleccionado = archivo;
                renderPreviewAdjunto();
            });
            $('#quitarAdjunto').on('click', quitarAdjuntoSeleccionado);

            $('#btnGrabar').on('click', iniciarGrabacion);
            $('#btnDetenerGrabacion').on('click', function () { detenerGrabacion(false); });
            $('#btnCancelarGrabacion').on('click', function () { detenerGrabacion(true); });

            renderEmojiPicker();
            $('#btnEmoji').on('click', function (e) {
                e.stopPropagation();
                toggleEmojiPicker();
            });
            $('#emojiPicker').on('click', function (e) { e.stopPropagation(); });
            $(document).on('click', cerrarEmojiPicker);

            $('#btnModoSeleccion').on('click', function () { activarModoSeleccion(null); });
            $('#btnCancelarSeleccion').on('click', cancelarModoSeleccion);
            $('#btnReenviarSeleccion').on('click', abrirModalReenviar);
            $('#cerrarModalReenviar').on('click', cerrarModalReenviar);
            $('#buscarReenviar').on('input', renderListaReenviar);
            $('#modalReenviar').on('click', function (e) {
                if (e.target === this) cerrarModalReenviar();
            });
        });
    </script>
</body>
</html>
<?php if (isset($CNX)) { $CNX->close(); } ?>
