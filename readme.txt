=== EpicPay for WooCommerce ===
Contributors: mimergt
Tags: WooCommerce, pagos, recurrente, checkout
Requires at least: 6.0
Tested up to: 6.8.1
Stable tag: 2.0.1
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
