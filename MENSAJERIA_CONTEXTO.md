# Mensajería empresarial y notificaciones — contexto exportado

> Exportado desde el proyecto `neo_encomiendas` el 2026-09-19, para arrancar el nuevo repo de Mensajería en servidor propio. Este documento resume decisiones, estado y pendientes tal como estaban en ese momento — verificar contra el código real antes de asumir que algo sigue vigente.

## 1. Origen y alcance

Iniciativa nacida dentro de `Neo_Encomiendas` (carpetas `Neo_Encomiendas/Mensajeria/` y `Neo_Encomiendas/Notificaciones/`), pensada como mensajería **interna** (`personal`↔`personal`), estilo chat tipo WhatsApp Web, más un sistema de notificaciones push del navegador. Mensajería con `clientes` (ej. vía WhatsApp Business API) quedó explícitamente para una fase posterior.

Motivo de la migración a repo/servidor propio (decidido 2026-09-19): el hosting actual de `Neo_Encomiendas` (files.mensisman.com.ar, panel File Manager) no tiene ningún directorio que sobreviva al botón "Desplegar nueva versión" — ese botón reemplaza TODO lo que hay en el docroot, incluidas carpetas hermanas del repo. Eso rompe cualquier estrategia de guardar adjuntos fuera del árbol versionado. El usuario decidió mudar el módulo completo a un servidor propio en vez de seguir peleando con esa limitación del hosting.

Dominio de producción original: `www.mensisman.com.ar` (proxeado por Cloudflare — `Server: cloudflare` en las respuestas HTTP).

## 2. Esquema de base de datos

### `notificaciones` (notificaciones internas, ej. para push)
- `Id` PK, `id_usuario_destino` (int, indexado), `Tipo` char255, `Titulo` char255, `Mensaje` text
- `Estado` tinyint: 0=No leída, 1=Leída
- `Prioridad` tinyint: 0=Normal, 1=Urgente
- `Fecha_Creacion`, `Fecha_Lectura` char(10)
- `Origen_Modulo` char255 — qué módulo generó la notificación
- `Canal_Envio` char255 — texto libre: "WhatsApp", "Email", "Sistema", etc. (el comentario de la columna en MySQL con códigos numéricos 1/2/3 está desactualizado, no aplica)
- `Url_Destino` — columna agregada después, para push (ver sección Notificaciones)
- Sin FKs; solo `UNIQUE KEY Id` y `KEY id_usuario_destino`.

### `mensajeria` (chat interno)
- `Id` PK, `id_usuario_origen` int, `id_usuario_destino` int
- `id_externo` text NOT NULL — ID del mensaje en sistema externo (WhatsApp, etc.), a futuro
- `Asunto` char255, `Contenido` text
- `Estado` tinyint: 0=No leído, 1=Leído
- `Fecha_Envio`/`Hora_Envio`, `Fecha_Lectura`/`Hora_Lectura`
- `Canal` char255 (se guarda `'Sistema'` para mensajes internos)
- `Respuesta_a` int NOT NULL sin default — hilo/cita a un mensaje puntual. **Ojo:** sin default, hay que mandar un valor explícito (ej. 0) al insertar si no es respuesta a nada, o falla el insert.
- Columnas de adjuntos agregadas después: `Adjunto_Archivo` char255, `Adjunto_Nombre` char255, `Adjunto_Tipo` char50 (todas NOT NULL DEFAULT '')
- Sin FKs; solo `UNIQUE KEY Id`.

Migración SQL de adjuntos:
```sql
ALTER TABLE mensajeria
  ADD COLUMN Adjunto_Archivo char(255) NOT NULL DEFAULT '' AFTER Contenido,
  ADD COLUMN Adjunto_Nombre char(255) NOT NULL DEFAULT '' AFTER Adjunto_Archivo,
  ADD COLUMN Adjunto_Tipo char(50) NOT NULL DEFAULT '' AFTER Adjunto_Nombre;
```

### `notificaciones_suscripciones` (Web Push)
Columnas: `id_usuario` (DNI), `Endpoint`, `Endpoint_Hash`, `P256dh`, `Auth`, `User_Agent`, fechas. El SQL exacto de creación quedó documentado en el plan `streamed-puzzling-puppy.md` de la sesión original (no reproducido acá — recrear el CREATE TABLE si hace falta, la estructura de columnas es la guía).

### Tablas de usuarios relevantes
No existe tabla `usuarios`. `personal` es la tabla de usuarios internos (con permisos: Levantes, Encomiendas, Cajas, etc.), **sin campo de email**. `clientes` tiene `Telefono` pero tampoco email — para notificar por Email a futuro falta agregar ese dato en algún lado.

## 3. Identidad de usuario / sesión

`$_SESSION['Operador']` = `personal.DNI` del usuario logueado (NO `personal.Id`) — ver `Neo_Encomiendas/login/index.php:104`. Por eso en `mensajeria`, `id_usuario_origen`/`id_usuario_destino` guardan el **DNI** de `personal` (ambos `int(11)`, consistente). El origen sale de `$_SESSION['Operador']`; también existe `$_SESSION['Alias']` en sesión. El selector de destinatario debe mostrar `personal.Alias` pero enviar/guardar `personal.DNI`.

Definición de hilo/conversación: **por par de usuarios (origen+destino)**, no por `Respuesta_a` raíz — la bandeja agrupa todos los mensajes entre el mismo par como una conversación continua tipo chat. `Respuesta_a` queda disponible para citar un mensaje puntual, pero no define la agrupación.

## 4. Backend implementado (carpeta `Mensajeria/`)

- **`Mensajeria_Enviar.php`** — POST Destino/Contenido/Asunto/Respuesta_a; valida que Destino exista en `personal` y no sea uno mismo; inserta con `Canal='Sistema'`, `Fecha_Envio=date('d/m/Y')`, `Hora_Envio=date('H:i:s')` (formatos elegidos para calzar con char(10)/char(8)). Contenido puede ir vacío si hay adjunto válido.
- **`Mensajeria_Bandeja.php`** — agrupa por par de usuarios (`CASE WHEN origen=yo THEN destino ELSE origen`), trae último mensaje + cuenta de no leídos; respeta `ONLY_FULL_GROUP_BY` agrupando solo por la expresión Contacto.
- **`Mensajeria_Conversacion.php`** — todos los mensajes entre el usuario logueado y un contacto puntual, ordenados por Id.
- **`Mensajeria_MarcarLeido.php`** — marca como leídos (Estado=1 + Fecha/Hora_Lectura) los mensajes pendientes de un contacto hacia el usuario logueado.
- **`Mensajeria_Adjunto.php`** — sirve el archivo adjunto solo si el usuario en sesión es origen o destino de ese mensaje puntual (403 si no). Setea `Content-Type` desde el mime detectado en servidor + `X-Content-Type-Options: nosniff`.
- **`Mensajeria_Reenviar.php`** — reenvía mensajes seleccionados; valida que todos los Ids pertenezcan a una conversación donde el usuario logueado participó (origen o destino) antes de reenviar. Cada reenvío crea una fila NUEVA en `mensajeria`; si el original tenía adjunto, reutiliza el mismo archivo físico (no lo duplica), protegido por la misma autorización por-mensaje.

**Convenciones seguidas** (calcadas de patrones ya existentes en `Neo_Encomiendas`, ej. `Levantes/pendientes_ajax.php` y `cajas/Cajas3.php`):
- Guard `if(!isset($_SESSION['Operador']))` → redirect a login (mensajería interna es para cualquier `personal` logueado, no restringido por rol específico).
- `require_once('../csrf.php')` + `csrf_verify()` en cada endpoint (excepto los que reciben llamadas del service worker, ver más abajo).
- `require_once('../Conexion.php')` + `mysqli_select_db`.
- Prepared statements (`bind_param`) en todas las queries.
- Respuesta `json_encode(['success' => bool, ...])` con `http_response_code` en error.

## 5. Frontend (`Mensajeria/index.php`)

Diseño tipo WhatsApp Web (a criterio propio, sin mockup de referencia):
- Sidebar izquierdo (header verde `#008069`, buscador, lista de contactos) + panel derecho de chat (burbujas verdes propias `#d9fdd3` a la derecha, blancas ajenas a la izquierda, input abajo).
- Lista de contactos combina TODO `personal` (excepto uno mismo) con el resumen de `Mensajeria_Bandeja.php`: conversaciones existentes primero (por fecha/hora desc.), después el resto de `personal` alfabético.
- Polling: bandeja cada 6s, conversación abierta cada 4s (comparando firma de Ids para no re-renderizar innecesariamente).
- Escapa HTML de Alias/Asunto/Contenido en cliente (`escapeHtml` vía jQuery `.text()`) antes de insertarlo — verificado contra XSS con `<script>alert(1)</script>` mostrado como texto plano.
- No usa el bundle JS de Bootstrap (no está cargado en esta página) — modales hand-rolled.

**Tilde de estado de lectura:** en mensajes propios, junto a la hora, un tilde (`bi-check`) gris `#8696a0` si `Estado=0` o azul `#53bdeb` si `Estado=1`. Un solo tilde (no doble check). Tooltip nativo (`title`) al pasar el mouse: "Leído a las HH:MM" (+fecha si fue otro día) o "No leído todavía". Alcance: solo en burbujas del chat, no en el preview de la bandeja.

**Sonido de mensaje nuevo:** archivo `Sonidos/Aviso_de_nuevo_mensaje.mp3`, reproducido con `new Audio('/ruta/Sonidos/Aviso_de_nuevo_mensaje.mp3')` (ruta absoluta desde raíz del dominio). Se dispara si, en el polling de bandeja (6s), el total de `NoLeidos` de todas las conversaciones SUBE respecto de la última lectura (indica mensaje entrante nuevo en cualquier conversación). No suena en la carga inicial ni cuando el total baja. **Gap conocido no arreglado:** si el chat ya está abierto y llega un mensaje nuevo por el polling de 4s de la conversación, no se marca como leído automáticamente (`marcarLeido()` solo se llama al abrir la conversación por primera vez) — por eso el sonido suena aunque el usuario esté mirando esa conversación.

## 6. Adjuntos (imágenes y PDF en el chat)

Formatos permitidos: JPG/PNG/GIF/WEBP/BMP y PDF. Explícitamente **no** se permiten otros formatos "porque no tenemos herramientas para evitar archivos maliciosos" (decisión del usuario).

- **Validación real de tipo:** `finfo_file()` sobre el contenido real del archivo subido, nunca por extensión ni por Content-Type del navegador (ambos falsificables). Probado: un `.jpg` que en realidad era texto plano fue rechazado.
- **SVG excluido a propósito** de los formatos de imagen: es XML y puede llevar `<script>` embebido que se ejecuta si se abre como documento en pestaña nueva. Solo se permiten formatos raster.
- **Almacenamiento:** nombre de archivo aleatorio (`bin2hex(random_bytes(16))` + extensión derivada del mime real detectado, nunca del nombre original) — evita path traversal / sobrescritura / ejecución. La carpeta de adjuntos tiene `.htaccess` con `Require all denied` (nadie accede por URL directa, todo pasa por `Mensajeria_Adjunto.php`).
- **Límite de tamaño:** 15MB, validado en cliente (feedback) y servidor (validación real).
- **Frontend:** botón de clip junto al textarea, preview antes de enviar (miniatura si imagen, ícono+nombre si PDF), envío vía `FormData`/`$.ajax` (no `$.post`, por `multipart/form-data`). En burbuja: imágenes inline (click abre pestaña nueva), PDFs como tarjeta con ícono rojo + nombre (abre visor nativo del navegador). En bandeja, si el último mensaje es solo adjunto, preview muestra "📷 Foto" o "📄 Documento PDF".

### Bug conocido: adjuntos borrados en cada despliegue (motivo original de la migración de servidor)

En el hosting anterior, el botón "Desplegar nueva versión" borraba/reemplazaba todo el contenido del docroot, incluida la carpeta de adjuntos (que nunca estuvo versionada en git a propósito, vía `.gitignore`).

**Fix de código ya aplicado** (para replicar en el nuevo servidor):
- Archivo `Mensajeria/adjuntos_config.php` (gitignored, mismo patrón que `Conexion.php`): un `return` con la ruta absoluta de la carpeta de adjuntos, definida por entorno.
- `Mensajeria_Enviar.php` y `Mensajeria_Adjunto.php` leen la ruta vía `require __DIR__ . '/adjuntos_config.php'` en vez de hardcodear `__DIR__ . '/adjuntos/'`.
- `.gitignore`: `Neo_Encomiendas/Mensajeria/adjuntos/*` con excepción `!Neo_Encomiendas/Mensajeria/adjuntos/.htaccess`, más la entrada de `adjuntos_config.php`.

**En el nuevo servidor propio, aplicar directamente:** crear una carpeta de adjuntos **fuera** de cualquier árbol que un futuro proceso de despliegue pudiera pisar, y que `adjuntos_config.php` apunte ahí desde el inicio (ej. `return '/home/usuario/adjuntos_mensajeria/';`), con permisos de escritura para el usuario de PHP/Apache. Como este es un servidor propio y un repo dedicado, conviene decidir esa estructura de carpetas antes de migrar el código, en vez de arrastrar el mismo problema.

## 7. Notificaciones Push (Web Push nativo, sin librerías)

Plan de implementación original: `C:\Users\Juancito\.claude\plans\streamed-puzzling-puppy.md` (en la máquina del usuario, puede no estar disponible en el nuevo entorno — recrear el detalle desde este resumen si hace falta).

- **VAPID implementado a mano con `openssl_*`** (sin Composer, porque la app principal no lo usaba y el despliegue era manual archivo por archivo — reevaluar si en el nuevo repo conviene usar Composer + una librería real de Web Push):
  - `generar_vapid_keys.php` (CLI) — genera un par de claves POR ENTORNO, la privada nunca se copia entre entornos.
  - `vapid_config.php` (gitignored, mismo patrón que `Conexion.php`).
  - `vapid_helper.php` — JWT ES256 + conversión de firma DER→raw + envío curl con VAPID.
- **Contacto VAPID (`sub`):** URL del sitio (`https://www.mensisman.com.ar` en el original — actualizar a la URL del nuevo servidor). Decisión explícita: nunca usar el email personal del usuario ahí.
- **Diseño push vacío + fetch** (no cifrado RFC 8291): el push no lleva payload, solo autenticación VAPID; el service worker, al recibir un push sin datos, hace `fetch` a `Notificacion_Pendientes.php` para buscar el contenido real antes de mostrar la notificación. Evita el cifrado AES-128-GCM completo de Web Push.
- **`Notificacion_Distribuye.php`** — librería con `Notificacion_Distribuye_Enviar($CNX, $datos)`: inserta en `notificaciones` y hace fan-out a Push por cada suscripción del usuario; borra suscripciones muertas (404/410) automáticamente.
- **Endpoints:** `Notificacion_Suscribir.php` (upsert de suscripción), `Notificacion_Pendientes.php` (GET, **sin CSRF** — lo lee el SW), `Notificacion_MarcarLeida.php` (POST, **sin CSRF a propósito** — lo llama el service worker sin acceso a token de pestaña abierta; mitigado por `SameSite=Strict` en la cookie de sesión).
- **`Notificaciones/.htaccess`:** `Service-Worker-Allowed: ../` para que el scope del SW cubra toda la app — **definir esto desde el arranque en el nuevo servidor**, porque cambiar el scope después de tener suscriptores reales rompe las suscripciones existentes.
- **`service_worker.js`:** push vacío→fetch→`showNotification` con `tag` por Id (evita duplicar); `notificationclick` marca como leída antes de enfocar/abrir ventana. Soporta `title`, `body`, `icon`, `badge`, `url`, `tag`, `requireInteraction` en el payload; ícono/badge default `/img/ENCOMIENDA.png` (cambiar por el ícono del nuevo proyecto).
- **`/js/notificaciones_push.js`:** registro del SW, pedido de permiso solo al click (nunca automático), `pushManager.subscribe()`, POST de la suscripción.

### Comportamiento por navegador (mensajes de error específicos en `notificaciones_push.js`)
- **Brave:** bloquea por defecto la comunicación con FCM de Google. Detecta `navigator.brave.isBrave()` + error "push service"/"registration failed" → indica activar en `brave://settings/privacy` la opción "Usar servicios de Google para la mensajería push". Confirmado por el usuario que esto resolvió el problema real que le apareció en producción.
- **Incógnito/privado:** detecta "incognito"/"private" en el mensaje de error, avisa que Push no funciona ahí.
- **iOS (iPhone/iPad):** Safari no expone la Push API si el sitio no fue agregado a la pantalla de inicio (ni siquiera en iOS 16.4+). Detectado por user-agent + `display-mode: standalone`/`navigator.standalone`; el botón explica el paso (Compartir → Agregar a inicio) en vez de fallar en silencio.
- **Opera/otros Chromium:** mensaje genérico, sin causa específica confirmada.

### Notas de testing con Playwright (relevante si se retoma testing automatizado en el nuevo repo)
- Chromium **headless** fuerza `Notification.permission` a `"denied"` sin importar permisos otorgados por Playwright — hace falta `headless:false`.
- Un `browser.newContext()` normal se comporta como incógnito y el Push API está deshabilitado ahí (`crbug.com/41124656`) — usar `chromium.launchPersistentContext()` con un directorio de perfil real.

## 8. Badge de mensajes sin leer en dashboard

Cuenta `COUNT(*) FROM mensajeria WHERE id_usuario_destino = ? AND Estado = 0` (server-side). En el original se mostraba en la página principal de `Neo_Encomiendas` (botón "Mensajes" en welcome-bar + badge rojo Bootstrap `bg-danger` sobre la card de Mensajería, capado a "99+"). Es un valor renderizado por PHP al cargar la página (sin polling) — si se quiere actualización en vivo, hace falta un endpoint liviano de conteo + `setInterval` (no implementado). Adaptar la ubicación de este badge al nuevo repo, que ya no vive dentro de `Neo_Encomiendas`.

## 9. Seguridad / temas pendientes de otras partes del sistema original

Estos no son parte del módulo de Mensajería en sí, pero son relevantes si se integra WhatsApp Business API en el nuevo repo:

- `Neo_Encomiendas/wtsp/comun.php` (en el repo original) tiene un **token de WhatsApp Business API (Facebook Graph) hardcodeado en texto plano y trackeado en git**. Si se retoma la integración de WhatsApp en el nuevo proyecto, no reutilizar ese patrón — mover a variable de entorno o config fuera del repo desde el inicio.
- El header `Permissions-Policy: microphone=()` que bloqueaba el micrófono para las notas de voz en producción **no está seteado en el código de la app** — lo pone el firewall/WAF del servidor de producción actual (NO Cloudflare, aunque el sitio esté proxeado por Cloudflare). En el servidor propio nuevo, verificar desde el inicio que no haya un header equivalente bloqueando `microphone`/`camera` antes de implementar notas de voz — usar `curl -D- https://tu-dominio` y revisar `Permissions-Policy`.

## 10. Pendientes al momento de la migración

- Notas de voz: feature implementada pero bloqueada en producción por el header `Permissions-Policy` mencionado arriba — al mudar a servidor propio, este bloqueo debería desaparecer (se controla la config del servidor), pero conviene verificarlo explícitamente antes de dar el feature por resuelta.
- Notificaciones por email: no implementado — falta agregar campo de email en `personal`/`clientes` si se quiere ese canal.
- Integración WhatsApp Business API real (mensajería con `clientes`): no iniciada, fuera de alcance del trabajo hecho hasta ahora.
- Actualización en vivo del badge de no leídos (sin recargar página): no implementado, requiere endpoint + polling.
- Gap del sonido/marcado de leído con chat abierto (sección 5): no arreglado.
- Reevaluar si conviene seguir con VAPID hecho a mano (`openssl_*`) o migrar a una librería estándar de Web Push ahora que es un repo propio y ya no hay restricción de "sin Composer".

## 11. Cómo retomar este tema con Claude en el nuevo repo

Al empezar en el nuevo directorio `/htdocs/mensajeria`, pegar este archivo como contexto inicial (ej. como memoria de proyecto o simplemente referenciarlo) antes de pedir cambios — reemplaza el historial de memoria acumulado en `neo_encomiendas`. Verificar contra el código real qué de esto sigue vigente después de la migración, ya que las rutas (`Neo_Encomiendas/Mensajeria/...`, `/img/ENCOMIENDA.png`, `Sonidos/...`) van a cambiar al vivir en un repo propio en vez de una subcarpeta de `Neo_Encomiendas`.
