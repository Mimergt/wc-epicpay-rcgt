<?php
/**
* Plugin Name: EpicPay - WooCommerce
* Plugin URI: https://github.com/Mimergt/wc-epicpay-rcgt
* Description: Plugin para WooCommerce que habilita la pasarela de pago EpicPay (Recurrente) como metodo de pago en el checkout.
* Version:     1.3.2
* Requires PHP: 7.4
* Author:      Mimer
* Author URI: https://epic.gt
* License:     MIT
* WC requires at least: 7.4.0
* WC tested up to: 8.7.0
*
* @package WoocommerceEpicPay
*/

if ( ! defined( 'ABSPATH' ) ) { 
  exit; // No permitir acceder el plugin directamente
}

/**
* Inicializa la pasarela de pagos EpicPay.
*/
function epicpay_init() {
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
  include_once ('classes/recurrente.php') ;

  EpicPay::get_instance();
}
add_action( 'plugins_loaded', 'epicpay_init', 0 );

/**
* Agrega EpicPay en la lista de pasarelas de pago.
*
* @return array
*/
function add_epicpay_gateway( $methods ) {
  $methods[] = 'EpicPay';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_epicpay_gateway' );

/**
* Agrega el enlace hacia la configuracion del plugin.
*
* @return array
*/
function epicpay_action_links( $links ) {
  $plugin_links = array(
	'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=epicpay' ) . '">' . __( 'Settings', 'epicpay' ) . '</a>',
  );
  return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'epicpay_action_links' );


/**
* Añade funcionalidad para compatibilidad con HPO de WooCommerce
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 1.2.0
*/
function epicpay_hpo(){
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
  }
} 
add_action('before_woocommerce_init', 'epicpay_hpo');

/**
* Añade funcionalidad para compatibilidad con Blocks de WooCommerce
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 1.2.0
*/
function declare_cart_checkout_blocks_compatibility() {
  // Check if the required class exists
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
      // Declare compatibility for 'cart_checkout_blocks'
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
  }
}
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

/**
* Añade funcionalidad para mostrar la pasarela de pagos en el area de bloques de WooCommerce
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 1.2.0
*/
function epicpay_register_order_approval_payment_method_type() {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
      return;
    }

    require_once ('includes/recurrente-block-checkout.php');

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
          $payment_method_registry->register( new WC_EpicPay_Blocks );
        }
    );
}
add_action( 'woocommerce_blocks_loaded', 'epicpay_register_order_approval_payment_method_type' );

/**
* Añade el ícono de tarjetas aceptadas a la pasarela de pago
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 1.2.0
*/
function filter_woocommerce_gateway_icon( $icon, $this_id ) {	
  if($this_id == "epicpay") {
		$icon = "<img style='max-width: 100px;' src='".plugins_url('assets/providers.png', __FILE__)."' alt='card providers' />";
	}
	return $icon;

}
add_filter( 'woocommerce_gateway_icon', 'filter_woocommerce_gateway_icon', 10, 2 );

/**
* Cambia el mensaje de confirmación dentro de WooCommerce
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 1.2.0
*/
function woo_change_order_received_text( $str, $order ) {
  $customer_order = wc_get_order( $order );
  return sprintf( "Gracias, %s!", esc_html( $customer_order->get_billing_first_name() ) );
}
add_filter('woocommerce_thankyou_order_received_text', 'woo_change_order_received_text', 10, 2 );

/**
* Agrega el tipo de producto recurrente al dropdown de productos
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 2.1.0
*/
function epicpay_add_custom_product_type( $types ){
  $types[ 'epicpay' ] = 'Producto EpicPay';
  return $types;
}
add_filter( 'product_type_selector', 'epicpay_add_custom_product_type' );

/**
* Agrega la clase del nuevo tipo de producto recurrente
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 2.1.0
*/
function epicpay_woocommerce_product_class( $classname, $product_type ) {
  if ( $product_type == 'epicpay' ) {
    $classname = 'epicpay_product';
  }
  return $classname;
}
add_filter( 'woocommerce_product_class', 'epicpay_woocommerce_product_class', 10, 2 );

/**
* Muestra el Tab de Precio al ser un producto no simple
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 2.1.0
*/
function epicpay_product_type_show_price() {
  global $product_object;
  if ( $product_object && 'epicpay' === $product_object->get_type() ) {
    wc_enqueue_js( "
      $('.product_data_tabs .general_tab').addClass('show_if_epicpay').show();
      $('.pricing').addClass('show_if_epicpay').show();
    ");
  }
}
add_action( 'woocommerce_product_options_general_product_data', 'epicpay_product_type_show_price' );

/**
* Agrega los valores del tab de productos recurrentes
* 
* @author Mimer
* @link https://github.com/Mimergt/wc-epicpay-rcgt
* @since 2.1.0
*/
function epicpay_product_tab_product_tab_content() {
 ?><div id='epicpay_product_options' class='panel woocommerce_options_panel'><?php
 ?><div class='options_group'><?php
                
    woocommerce_wp_text_input(
    array(
      'id' => 'epicpay_price',
      'label' => __( 'Precio', 'epicpay' ),
      'placeholder' => '',
      'desc_tip' => 'true',
      'description' => __( 'Ingrese el precio de la suscripcion.', 'epicpay' ),
      'type' => 'number'
    )
    );
 ?></div>
 </div><?php
}
add_action( 'woocommerce_product_data_panels', 'epicpay_product_tab_product_tab_content' );
