<?php
/**
* Clase para obtener la configuracion de EpicPay.
*/
class EpicPaySettings
{
    /**
    * Obtiene el arreglo de configuraciones
    * 
    * @author Mimer
    * @author Franco A. Cabrera <francocabreradev@gmail.com>
    * @return Array  Arreglo de campos para la vista de configuración
    * @since 1.2.0
    * Actualizado en la 2.1.1
    */ 
    public static function get_settings(){
        return array(
            'enabled' => array(
              'title'    => __( 'Activar / Desactivar', 'epicpay' ),
              'label'    => __( 'Activa la pasarela de pago', 'epicpay' ),
              'type'    => 'checkbox',
              'default'  => 'no',
            ),
            'title' => array(
              'title'    => __( 'Titulo', 'epicpay' ),
              'type'    => 'text',
              'desc_tip'  => __( 'Titulo a mostrar en el checkout.', 'epicpay' ),
              'default'  => __( 'Pago con tarjeta', 'epicpay' ),
            ),
            'description' => array(
              'title'    => __( 'Descripcion', 'epicpay' ),
              'type'    => 'text',
              'desc_tip'  => __( 'Descripcion a mostrar en el checkout.', 'epicpay' ),
              'default'  => __( 'Procesa tu pago a traves de EpicPay', 'epicpay' )
            ),
            'enable_subscriptions' => array(
              'title'    => __( 'Compatibilidad con suscripciones', 'epicpay' ),
              'label'    => __( 'Activar compatibilidad con WooCommerce Subscriptions', 'epicpay' ),
              'type'     => 'checkbox',
              'default'  => 'yes',
              'desc_tip' => __( 'Opcional: desactiva esta opción si quieres que EpicPay solo procese pagos de una sola vez.', 'epicpay' ),
            ),
            'environment' => array(
              'title'       => __( 'Entorno', 'epicpay' ),
              'type'        => 'select',
              'description' => __( 'Selecciona si usaras llaves de pruebas (sandbox) o produccion (live).', 'epicpay' ),
              'options'     => array(
                'sandbox' => __( 'Sandbox (pruebas)', 'epicpay' ),
                'live'    => __( 'Live (produccion)', 'epicpay' ),
              ),
              'default'     => 'sandbox',
            ),
            'sandbox_public_key' => array(
              'title'    => __( 'Clave publica sandbox', 'epicpay' ),
              'type'     => 'text',
              'desc_tip' => __( 'Llave publica para pruebas (test).', 'epicpay' ),
            ),
            'sandbox_secret_key' => array(
              'title'    => __( 'Clave secreta sandbox', 'epicpay' ),
              'type'     => 'text',
              'desc_tip' => __( 'Llave secreta para pruebas (test).', 'epicpay' ),
            ),
            'live_public_key' => array(
              'title'    => __( 'Clave publica live', 'epicpay' ),
              'type'     => 'text',
              'desc_tip' => __( 'Llave publica para produccion.', 'epicpay' ),
            ),
            'live_secret_key' => array(
              'title'    => __( 'Clave secreta live', 'epicpay' ),
              'type'     => 'text',
              'desc_tip' => __( 'Llave secreta para produccion.', 'epicpay' ),
            ),
            'public_key' => array(
              'title'    => __( 'Clave publica (legado)', 'epicpay' ),
              'type'    => 'text',
              'desc_tip'  => __( 'Campo legacy para compatibilidad con configuraciones anteriores.', 'epicpay' ),
            ),
            'secret_key' => array(
              'title'    => __( 'Clave secreta (legado)', 'epicpay' ),
              'type'    => 'text',
              'desc_tip'  => __( 'Campo legacy para compatibilidad con configuraciones anteriores.', 'epicpay' ),
            ),
            'allow_transfer' => array(
              'title'    => __( 'Habilitar transferencia bancaria', 'epicpay' ),
              'label'    => __( 'Activa la opcion de pago por transferencia bancaria.', 'epicpay' ),
              'type'    => 'checkbox',
              'default'  => 'no',
              'desc_tip'  => __( 'Esta opcion muestra transferencia bancaria como posible opcion de pago.', 'epicpay' ),
            ),
            'installments' => array(
              'title'    => __( 'Habilitar cuotas', 'epicpay' ),
              'type'    => 'multiselect',
              'options'     => array( // Array of options for select/multiselect inputs only.
                '3 Meses' => '3',
                '6 Meses' => '6',
                '12 Meses' => '12',
                '18 Meses' => '18'
              ),
              'desc_tip'  => __( 'Presiona la opcion + CTRL para seleccionar varias.', 'epicpay' ),
            ),
            'order_status' => array(
                'title'       => __( 'Estado predeterminado de la orden', 'epicpay' ),
                'type'        => 'select',
                'description' => __( 'Selecciona el estado predeterminado para las ordenes procesadas.', 'epicpay' ),
                'options'     => array(
                    'wc-completed'  => __( 'Completada', 'epicpay' ),
                    'wc-on-hold'    => __( 'En espera', 'epicpay' ),
                    'wc-cancelled'  => __( 'Cancelada', 'epicpay' ),
                ),
                'default'     => 'wc-completed',
            ),
        );    
    }
}