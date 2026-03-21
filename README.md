# EpicPay for WooCommerce

Plugin de WooCommerce para integrar pagos con EpicPay usando la API de Recurrente.

## Estado

Fase Uno en progreso.

Incluye:

- Rebranding inicial a Mimer / EpicPay / EPIC.gt.
- Limpieza de dependencias heredadas.
- Integracion de checkout clasico y WooCommerce Blocks.
- Cliente HTTP migrado a WordPress HTTP API.

No incluye aun:

- Soporte de suscripciones (Fase Dos).

## Requisitos

- WordPress 6.0+
- WooCommerce 7.4+
- PHP 7.4+

## Instalacion

```bash
git clone https://github.com/Mimergt/wc-epicpay-rcgt.git
```

1. Comprime el proyecto o copialo a wp-content/plugins.
2. Activa el plugin en WordPress.
3. Abre WooCommerce > Ajustes > Pagos > EpicPay.
4. Configura `X-PUBLIC-KEY` y `X-SECRET-KEY`.

## API base

Por defecto la API usa:

`https://app.recurrente.com/api`

Puedes sobrescribirla con el filtro de WordPress:

```php
add_filter('epicpay_api_base_url', function () {
   return 'https://app.recurrente.com/api';
});
```

## Roadmap

- Fase Uno: base limpia + rebranding + hardening inicial.
- Fase Dos: soporte subscriptions.
