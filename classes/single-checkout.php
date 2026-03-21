<?php
/**
* Clase para interactuar con un Checkou de Cobro único dentro de Recurrente
*
* Objeto principal para interactuar con un checkout de Cobro único dentro de recurrente.
*
* @copyright  2024 - tipi(code)
* @since      2.0.1
*/ 
class Single_Checkout {
    private $gateway;
    private $customer_order;
    public $id;
    public $url;
    public $product;
    public $code;

    /**
    * Constructor
    *
    * @param WC_Order  $customer_order  Orden de WooCommerce para procesar los datos del producto.
    * 
    */ 
    function __construct($customer_order) {
        $this->gateway = EpicPay::get_instance();
        $this->customer_order = $customer_order;
    }

    /**
    * Crea un nuevo Checkout de cobro único
    * 
    * @throws Exception Si la llamada a recurrente falla
    * @author Mimer
    * @return string HTTP Response Code de la llamada
    * @link https://github.com/Mimergt/wc-epicpay-rcgt
    * @since 2.0.0
    */
    public function create(){
        try{
            $url = trailingslashit( $this->get_api_base_url() ) . 'checkouts';
            $checkout = $this->get_api_model();//Obtiene objeto en formato JSON como lo requiere Recurrente
            $response = wp_remote_post(
                $url,
                array(
                    'headers' => $this->get_headers(),
                    'body' => wp_json_encode( $checkout ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $this->code = (int) wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ) );

            if ( 201 === $this->code ) {
                $this->id = isset( $body->id ) ? $body->id : null;
                $this->product = isset( $body->product ) ? $body->product : null;
                $this->url = isset( $body->checkout_url ) ? $body->checkout_url : ( isset( $body->url ) ? $body->url : null );

                if ( empty( $this->url ) ) {
                    return new WP_Error( 'epicpay_checkout_url_missing', __( 'Recurrente no devolvio checkout_url.', 'epicpay' ) );
                }

                return true;
            }

            $error_message = isset( $body->error ) ? $body->error : ( isset( $body->message ) ? $body->message : __( 'Error al crear checkout.', 'epicpay' ) );
            $public_preview = substr( (string) $this->gateway->public_key, 0, 8 );
            error_log( 'EpicPay Single_Checkout API Error (HTTP ' . $this->code . '): ' . $error_message . ' | pk=' . $public_preview . '...' );
            return new WP_Error( 'epicpay_checkout_create_failed', $error_message );

        } catch (Exception $e) {
			return new WP_Error('error', $e->getMessage());
		}
    }

    /**
    * Elimina un producto de la biblioteca de Recurrente
    * 
    * @throws Exception Si la llamada a recurrente falla
    * @author Mimer
    * @return string HTTP Response Code de la llamada
    * @link https://github.com/Mimergt/wc-epicpay-rcgt
    * @since 2.0.0
    */
    public function clean(){
        try{
            $url = trailingslashit( $this->get_api_base_url() ) . 'products/' . $this->id;
            $response = wp_remote_request(
                $url,
                array(
                    'method' => 'DELETE',
                    'headers' => $this->get_headers(),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            return (int) wp_remote_retrieve_response_code( $response );

        } catch (Exception $e) {
			return new WP_Error('error', $e->getMessage());
		}
    }

    /**
    * Obtiene el modelo de un checkout para poder interactual con el API de recurrente
    * 
    * @author Mimer
    * @author Franco A. Cabrera <francocabreradev@gmail.com>
    * @return Array Objeto para usar con el API de Recurrente
    * @link https://github.com/Mimergt/wc-epicpay-rcgt
    * @since 2.0.1
    */ 
    private function get_api_model(){
        $amount_in_cents = (int) round( (float) $this->customer_order->get_total() * 100 );
        $order_id = $this->customer_order->get_id();

        return array(
            'items' => array(
                array(
                    'name' => sprintf( 'Orden %s', $this->customer_order->get_order_number() ),
                    'amount_in_cents' => $amount_in_cents,
                    'currency' => $this->customer_order->get_currency(),
                    'quantity' => 1,
                ),
            ),
            'success_url' => add_query_arg(
                array(
                    'wc-api' => 'epicpay',
                    'status' => 1,
                    'order' => $order_id,
                ),
                home_url( '/' )
            ),
            'cancel_url' => add_query_arg(
                array(
                    'wc-api' => 'epicpay',
                    'status' => 0,
                    'order' => $order_id,
                ),
                home_url( '/' )
            ),
            'metadata' => array(
                'order_id' => (string) $order_id,
                'order_number' => (string) $this->customer_order->get_order_number(),
            ),
        );
    }

    private function get_api_base_url() {
        $default_base_url = 'https://app.recurrente.com/api';
        return untrailingslashit( apply_filters( 'epicpay_api_base_url', $default_base_url ) );
    }

    private function get_headers() {
        $public_key = trim( (string) $this->gateway->public_key );
        $secret_key = trim( (string) $this->gateway->secret_key );

        return array(
            'X-PUBLIC-KEY' => $public_key,
            'X-SECRET-KEY' => $secret_key,
            'X-ORIGIN' => site_url(),
            'X-STORE' => get_bloginfo( 'name' ),
            'Content-Type' => 'application/json',
        );
    }
}