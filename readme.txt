=== EpicPay for WooCommerce ===
Contributors: mimergt
Tags: WooCommerce, pagos, recurrente, checkout
Requires at least: 6.0
Tested up to: 6.8.1
Stable tag: 1.3.6
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
5. Configura la X-SECRET-KEY de Recurrente segun tu entorno.

== Changelog ==

= 1.3.6 =
* Se fortalecio la resolucion de la llave secreta activa (trim + fallback entre campos) para evitar fallos de autenticacion por configuracion de entorno.
* Se simplificaron los headers del checkout para enviar solo lo requerido por la API: X-SECRET-KEY.

= 1.3.5 =
* La integracion de API ahora autentica usando unicamente X-SECRET-KEY.
* Se removieron de configuracion los campos de llaves publicas para evitar errores de autenticacion.

= 1.3.4 =
* Checkout blocks ahora usa la descripcion configurada en la pasarela (en lugar de la descripcion tecnica interna del plugin).
* Se agrego versionado del script de bloques para invalidar cache y reflejar cambios del boton de pago inmediatamente.

= 1.3.3 =
* Se actualizo el texto del boton de pago en checkout blocks a "Pagar de forma segura".

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
