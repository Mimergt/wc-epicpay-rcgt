<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
/**
* Clase que habilita el soporte para el constructor de bloques de Wordpress
*
* Habilita los ¨Blocks¨ dentro de la vista de Checkout, para poder ampliar la compatibilidad
* del plugin.
*
* @copyright  2024 - tipi(code)
* @since      1.2.0
*/ 
final class WC_EpicPay_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'epicpay';

    public function initialize() {
        $this->settings = get_option( 'epicpay_settings', [] );
        $this->gateway = EpicPay::get_instance();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
    * Registra el Script para que se despliegue en el UI
    * 
    * @author Mimer
    * @return string Integracion con el sistema de bloques.
    * @link https://github.com/Mimergt/wc-epicpay-rcgt
    * @since 1.2.0
    */ 
    public function get_payment_method_script_handles() {

        wp_register_script(
            'epicpay-blocks-integration',
            plugin_dir_url(__FILE__) . 'block/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'epicpay-blocks-integration' );
            
        }
        return [ 'epicpay-blocks-integration' ];
    }

    /**
    * Obtiene la información a ser utilizada en el UI
    * 
    * @author Mimer
    * @return Array Propiedades de la pasarela de pago.
    * @link https://github.com/Mimergt/wc-epicpay-rcgt
    * @since 1.2.0
    */ 
    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->method_description,
            'icon' => $this->gateway->icon,
        ];
    }

}