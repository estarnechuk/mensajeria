<?php
// Ruta absoluta de la carpeta donde se guardan los adjuntos del chat de Mensajería.
// Archivo gitignored (mismo patrón que Conexion.php): cada entorno define la suya.
// En producción debe apuntar FUERA del árbol que gestiona "Desplegar nueva versión",
// para que ese despliegue nunca pueda borrar los archivos subidos por los usuarios.
return __DIR__ . '/adjuntos/';
