=== EpicPay for WooCommerce ===
Contributors: mimergt
Tags: WooCommerce, pagos, recurrente, checkout
Requires at least: 6.0
Tested up to: 6.8.1
Stable tag: 2.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin para WooCommerce que habilita EpicPay (Recurrente) como metodo de pago en el checkout.

== Description ==

EpicPay para WooCommerce integra pagos con Recurrente para cobros en linea desde checkout clasico y checkout blocks.

Esta version corresponde a la Fase Uno del producto derivado: rebranding, limpieza tecnica y base para pruebas rapidas.

== Installation ==

1. Instala y activa WooCommerce.
2. Sube este plugin como ZIP o copia la carpeta en wp-content/plugins.
3. Activa EpicPay for WooCommerce.
4. Ve a WooCommerce > Ajustes > Pagos > EpicPay.
5. Configura X-PUBLIC-KEY y X-SECRET-KEY de Recurrente.

== Changelog ==

= 2.1.1 =
* Fix para "Añadir método de pago": cuando Recurrente rechaza tokenización con monto 0 por validación de mínimo, ahora se reintenta automáticamente en modo setup sin items.
* Ajuste de `user_id` en tokenización: solo se envía si existe un `recurrente user_id` válido (`us_...`).

= 2.1.0 =
* Nueva funcionalidad: guardar tarjeta del cliente desde Mi cuenta > Métodos de pago usando checkout de tokenización (monto 0) en Recurrente.
* Se agrega soporte `tokenization` y `add_payment_method` en el gateway EpicPay.
* Nuevo callback para tokenización que consulta `GET /api/checkouts/{id}` y guarda el `payment_method_id` como token de WooCommerce.
* Se establece la tarjeta guardada como método predeterminado del cliente dentro de WooCommerce.
* Backup creado antes de esta versión: rama `backup-2-0-7-estado-ok` y tag `backup-v2.0.7-estado-ok`.

= 2.0.7 =
* Se eliminó el enmascarado de llaves API en la pantalla de configuración para evitar guardar valores con asteriscos.
* Se mantiene validación estricta de credenciales para detectar llaves inválidas antes de llamar al API.

= 2.0.6 =
* Validación preventiva de llaves activas antes de llamar al API (detecta llaves enmascaradas y desajuste de entorno test/live).
* Logging de diagnóstico en WooCommerce Logs con source `epicpay` para análisis de errores de autenticación.
* Mensajes de error más claros al cliente cuando la autenticación falla por configuración.

= 2.0.5 =
* Fix de compatibilidad PHP 8.2+: se declara la propiedad `enable_subscriptions` para evitar warning de propiedad dinámica.
* Logging de diagnóstico reforzado en process_payment para validar entorno y llaves activas en tiempo de pago.
* Logging extendido de respuesta API en checkout de suscripciones para diagnosticar errores de autenticación.

= 2.0.4 =
* Nuevo ajuste opcional en admin para activar/desactivar compatibilidad con WooCommerce Subscriptions.
* Mejor deteccion de ordenes con suscripcion en process_payment.
* Normalizacion (`trim`) de llaves API antes de enviar headers a Recurrente.
* Correccion en extraccion de montos para suscripciones (`WC_Subscriptions_Order`).
* Logging mejorado para diagnosticar errores de autenticacion/API al crear checkout.

= 2.0.3 =
* Cache busting para Checkout Blocks: el script de integracion ahora se versiona con filemtime para evitar JS viejo en navegador/CDN.

= 2.0.2 =
* Fix para Checkout Blocks: ahora se envian correctamente las features/suportes del gateway a la integracion de bloques.
* Correccion para compatibilidad con carritos de suscripcion donde el metodo no aparecia en "Opciones de pago".

= 2.0.0 =
* Agregar soporte completo para WooCommerce Subscriptions.
* Nueva clase Subscription_Checkout para manejar pagos iniciales de suscripciones.
* Soporte para pagos programados (renovaciones automáticas) vía WC Subscriptions.
* Soporte para ciclos de prueba gratuitos (free trials).
* Gestión de suscripciones: cancelación, suspensión, reactivación.
* Cambios de monto, fecha y método de pago en suscripciones.

= 1.3.2 =
* El estado configurado ahora se aplica correctamente tras el pago exitoso.
* Las llaves API se muestran enmascaradas en configuracion dejando visibles solo los primeros y ultimos 4 caracteres.

= 1.3.1 =
* Visibilidad dinamica de campos de llaves segun entorno Sandbox/Live.
* Campos legacy ocultos en UI para reducir confusion.

= 1.3.0 =
* Nuevo selector de entorno Sandbox/Live para usar llaves separadas.
* Fallback de compatibilidad para llaves legacy existentes.

= 1.2.0 =
* Rebranding inicial a EpicPay.
* Eliminado update checker heredado.
* Migracion de cliente HTTP a WordPress HTTP API.
* Ajustes base de seguridad en callback/webhook.
