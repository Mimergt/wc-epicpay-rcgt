<?php
/**
 * Clase para manejar checkouts de suscripción en Recurrente
 *
 * Extiende la funcionalidad para suscripciones,
 * extrayendo datos de WooCommerce Subscriptions.
 *
 * @since 2.0.0
 */
class Subscription_Checkout {
	private $gateway;
	private $customer_order;
	public $id;
	public $url;
	public $product;
	public $code;
	private $recurrente_products = array();

	/**
	 * Constructor
	 *
	 * @param WC_Order $customer_order Orden de WooCommerce para procesar los datos del producto.
	 */
	function __construct( $customer_order ) {
		$this->gateway        = EpicPay::get_instance();
		$this->customer_order = $customer_order;
		$this->load_recurrente_products();
	}

	/**
	 * Carga productos de Recurrente con soporte para dynamic pricing
	 * Necesarios para crear checkouts de suscripción
	 *
	 * @return void
	 */
	private function load_recurrente_products() {
		// Obtener productos cacheados o hacer llamada a API
		$cached_products = get_transient( 'epicpay_recurrente_products' );
		
		if ( ! empty( $cached_products ) ) {
			$this->recurrente_products = $cached_products;
			return;
		}

		// Obtener productos de Recurrente API
		try {
			$url      = trailingslashit( $this->get_api_base_url() ) . 'products';
			$response = wp_remote_get(
				$url,
				array(
					'headers' => $this->get_headers(),
					'timeout' => 30,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$status_code = (int) wp_remote_retrieve_response_code( $response );
				if ( 200 !== $status_code ) {
					error_log( 'EpicPay: Error consultando productos Recurrente (HTTP ' . $status_code . ')' );
					return;
				}

				$body     = json_decode( wp_remote_retrieve_body( $response ), true );
				$products = is_array( $body ) ? $body : array();

				// Filtrar productos recurrentes con dynamic pricing
				$this->recurrente_products = array_filter(
					$products,
					function ( $product ) {
						if ( empty( $product['has_dynamic_pricing'] ) || empty( $product['prices'] ) || ! is_array( $product['prices'] ) ) {
							return false;
						}

						foreach ( $product['prices'] as $price ) {
							if ( isset( $price['charge_type'] ) && 'recurring' === $price['charge_type'] ) {
								return true;
							}
						}

						return false;
					}
				);

				// Cachear por 6 horas
				set_transient( 'epicpay_recurrente_products', $this->recurrente_products, 6 * HOUR_IN_SECONDS );
			}
		} catch ( Exception $e ) {
			// Log error but don't fail - will use generic checkout
			error_log( 'EpicPay: Error cargando productos de Recurrente: ' . $e->getMessage() );
		}
	}

	/**
	 * Crea checkout para suscripción (pago inicial)
	 *
	 * @throws Exception Si la llamada a recurrente falla
	 * @return bool|WP_Error
	 */
	public function create() {
		try {
			// Validar credenciales
			if ( empty( $this->gateway->public_key ) || empty( $this->gateway->secret_key ) ) {
				return new WP_Error( 
					'epicpay_missing_credentials', 
					__( 'Credenciales de EpicPay no configuradas.', 'epicpay' ) 
				);
			}

			$url     = trailingslashit( $this->get_api_base_url() ) . 'checkouts';
			$checkout = $this->get_api_model();

			if ( empty( $checkout ) ) {
				return new WP_Error( 
					'epicpay_checkout_model_empty', 
					__( 'No se pudo crear modelo de checkout.', 'epicpay' ) 
				);
			}

			$response = wp_remote_post(
				$url,
				array(
					'headers' => $this->get_headers(),
					'body'    => wp_json_encode( $checkout ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'EpicPay Subscription Error: ' . $response->get_error_message() );
				return $response;
			}

			$this->code = (int) wp_remote_retrieve_response_code( $response );
			$body       = json_decode( wp_remote_retrieve_body( $response ) );

			if ( 201 === $this->code ) {
				$this->id      = isset( $body->id ) ? $body->id : null;
				$this->product = isset( $body->product ) ? $body->product : null;
				$this->url     = isset( $body->checkout_url ) ? $body->checkout_url : ( isset( $body->url ) ? $body->url : null );

				if ( empty( $this->url ) ) {
					error_log( 'EpicPay: Recurrente no devolvió checkout_url en respuesta: ' . print_r( $body, true ) );
					return new WP_Error( 'epicpay_checkout_url_missing', __( 'Recurrente no devolvió checkout_url.', 'epicpay' ) );
				}

				return true;
			}

			$error_message = isset( $body->error ) ? $body->error : ( isset( $body->message ) ? $body->message : __( 'Error al crear checkout.', 'epicpay' ) );
			$raw_body = wp_remote_retrieve_body( $response );
			error_log( 'EpicPay Subscription API Error (Code ' . $this->code . '): ' . $error_message . ' | response=' . $raw_body );
			
			return new WP_Error( 'epicpay_checkout_create_failed', $error_message );

		} catch ( Exception $e ) {
			error_log( 'EpicPay Subscription Exception: ' . $e->getMessage() );
			return new WP_Error( 'error', $e->getMessage() );
		}
	}

	/**
	 * Obtiene modelo API para suscripción
	 * Usa productos de Recurrente con dynamic pricing si están disponibles
	 *
	 * @return array
	 */
	private function get_api_model() {
		// Extraer información de suscripción si WC_Subscriptions existe
		$initial_payment   = $this->safe_get_subscription_meta( 'initial_payment' );
		$price_per_period  = $this->safe_get_subscription_meta( 'price_per_period' );
		$sign_up_fee       = $this->safe_get_subscription_meta( 'sign_up_fee' );

		// El monto del checkout es el pago inicial
		$checkout_amount = ! empty( $initial_payment ) ? $initial_payment : $price_per_period;
		if ( empty( $checkout_amount ) ) {
			$checkout_amount = (float) $this->customer_order->get_total();
		}
		$amount_in_cents = (int) round( (float) $checkout_amount * 100 );

		$order_id   = $this->customer_order->get_id();
		$order_num  = $this->customer_order->get_order_number();

		// Intentar usar primer producto con dynamic pricing de Recurrente
		$product_id = ! empty( $this->recurrente_products ) ? reset( $this->recurrente_products )['id'] : null;

		$checkout_data = array(
			'items'       => array(
				array(
					'name'              => sprintf( 'Suscripción - Orden %s', $order_num ),
					'amount_in_cents'   => $amount_in_cents,
					'currency'          => $this->customer_order->get_currency(),
					'quantity'          => 1,
				),
			),
			'success_url' => add_query_arg(
				array(
					'wc-api' => 'epicpay',
					'status' => 1,
					'order'  => $order_id,
					'type'   => 'subscription',
				),
				home_url( '/' )
			),
			'cancel_url'  => add_query_arg(
				array(
					'wc-api' => 'epicpay',
					'status' => 0,
					'order'  => $order_id,
					'type'   => 'subscription',
				),
				home_url( '/' )
			),
			'metadata'    => array(
				'order_id'           => (string) $order_id,
				'order_number'       => (string) $order_num,
				'subscription'       => 'true',
				'initial_payment'    => (string) $initial_payment,
				'price_per_period'   => (string) $price_per_period,
				'sign_up_fee'        => (string) $sign_up_fee,
				'wc_subscription'    => 'true',
			),
		);

		// Si tenemos producto de Recurrente con dynamic pricing, incluirlo
		if ( ! empty( $product_id ) ) {
			$checkout_data['product_id'] = $product_id;
			$checkout_data['metadata']['recurrente_product_id'] = $product_id;
		}

		return $checkout_data;
	}

	/**
	 * Obtiene datos de suscripción de forma segura
	 * Maneja caso cuando WC_Subscriptions no está disponible
	 *
	 * @param string $meta_key Clave de metadata.
	 * @return mixed|null
	 */
	private function safe_get_subscription_meta( $meta_key ) {
		// Verificar si WC_Subscriptions está disponible
		if ( ! function_exists( 'wcs_is_subscription' ) || ! class_exists( 'WC_Subscriptions_Order' ) ) {
			return null;
		}

		try {
			$map = array(
				'initial_payment' => 'get_total_initial_payment',
				'price_per_period' => 'get_price_per_period',
				'sign_up_fee' => 'get_sign_up_fee',
			);

			if ( ! isset( $map[ $meta_key ] ) ) {
				return null;
			}

			$method = $map[ $meta_key ];
			if ( method_exists( 'WC_Subscriptions_Order', $method ) ) {
				return call_user_func( array( 'WC_Subscriptions_Order', $method ), $this->customer_order );
			}
		} catch ( Exception $e ) {
			error_log( 'EpicPay: Error obteniendo meta de suscripción: ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Obtiene URL base de API
	 *
	 * @return string
	 */
	private function get_api_base_url() {
		$default_base_url = 'https://app.recurrente.com/api';
		return untrailingslashit( apply_filters( 'epicpay_api_base_url', $default_base_url ) );
	}

	/**
	 * Obtiene headers para llamada a API
	 *
	 * @return array
	 */
	private function get_headers() {
		$public_key = trim( (string) $this->gateway->public_key );
		$secret_key = trim( (string) $this->gateway->secret_key );

		return array(
			'X-PUBLIC-KEY'  => $public_key,
			'X-SECRET-KEY'  => $secret_key,
			'X-ORIGIN'      => site_url(),
			'X-STORE'       => get_bloginfo( 'name' ),
			'Content-Type'  => 'application/json',
		);
	}
}
?>
