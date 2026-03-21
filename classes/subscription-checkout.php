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

	/**
	 * Constructor
	 *
	 * @param WC_Order $customer_order Orden de WooCommerce para procesar los datos del producto.
	 */
	function __construct( $customer_order ) {
		$this->gateway        = EpicPay::get_instance();
		$this->customer_order = $customer_order;
	}

	/**
	 * Crea checkout para suscripción (pago inicial)
	 *
	 * @throws Exception Si la llamada a recurrente falla
	 * @return bool|WP_Error
	 */
	public function create() {
		try {
			$url     = trailingslashit( $this->get_api_base_url() ) . 'checkouts';
			$checkout = $this->get_api_model();

			$response = wp_remote_post(
				$url,
				array(
					'headers' => $this->get_headers(),
					'body'    => wp_json_encode( $checkout ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$this->code = (int) wp_remote_retrieve_response_code( $response );
			$body       = json_decode( wp_remote_retrieve_body( $response ) );

			if ( 201 === $this->code ) {
				$this->id      = isset( $body->id ) ? $body->id : null;
				$this->product = isset( $body->product ) ? $body->product : null;
				$this->url     = isset( $body->checkout_url ) ? $body->checkout_url : ( isset( $body->url ) ? $body->url : null );

				if ( empty( $this->url ) ) {
					return new WP_Error( 'epicpay_checkout_url_missing', __( 'Recurrente no devolvió checkout_url.', 'epicpay' ) );
				}

				return true;
			}

			$error_message = isset( $body->error ) ? $body->error : ( isset( $body->message ) ? $body->message : __( 'Error al crear checkout.', 'epicpay' ) );
			return new WP_Error( 'epicpay_checkout_create_failed', $error_message );

		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}
	}

	/**
	 * Obtiene modelo API para suscripción
	 * Extrae datos del pedido usando WC_Subscriptions_Order helpers
	 *
	 * @return array
	 */
	private function get_api_model() {
		// Extraer información de suscripción
		$initial_payment   = WC_Subscriptions_Order::get_total_initial_payment( $this->customer_order );
		$price_per_period  = WC_Subscriptions_Order::get_price_per_period( $this->customer_order );
		$sign_up_fee       = WC_Subscriptions_Order::get_sign_up_fee( $this->customer_order );

		// El monto del checkout es el pago inicial
		$checkout_amount = ! empty( $initial_payment ) ? $initial_payment : $price_per_period;
		$amount_in_cents = (int) round( (float) $checkout_amount * 100 );

		$order_id = $this->customer_order->get_id();

		return array(
			'items'       => array(
				array(
					'name'              => sprintf( 'Suscripción - Orden %s', $this->customer_order->get_order_number() ),
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
				'order_number'       => (string) $this->customer_order->get_order_number(),
				'subscription'       => 'true',
				'initial_payment'    => (string) $initial_payment,
				'price_per_period'   => (string) $price_per_period,
				'sign_up_fee'        => (string) $sign_up_fee,
			),
		);
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
		return array(
			'X-PUBLIC-KEY'  => $this->gateway->public_key,
			'X-SECRET-KEY'  => $this->gateway->secret_key,
			'X-ORIGIN'      => site_url(),
			'X-STORE'       => get_bloginfo( 'name' ),
			'Content-Type'  => 'application/json',
		);
	}
}
?>
