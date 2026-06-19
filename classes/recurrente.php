<?php
/**
* Clase principal para interactuar con el API de EpicPay (Recurrente).
*/
class EpicPay extends WC_Payment_Gateway {
  public $environment;
  public $secret_key;
  public $sandbox_secret_key;
  public $live_secret_key;
  public $allow_transfer;
  public $installments;
  public $order_status;
  private static $instance;

  /**
  * Constructor
  * 
  */ 
  function __construct() {
    // Id global
    $this->id = 'epicpay';
    // titulo a mostrar
    $this->method_title = __( 'EpicPay', 'epicpay' );
    // Descripcion a mostrar
    $this->method_description = __( 'Plugin de EpicPay para WooCommerce', 'epicpay' );
    // Seccion de tabs verticales
    $this->title = __( 'EpicPay', 'epicpay' );
    $this->icon = $this->get_option('icon');
    $this->has_fields = false;
    $this->description = "<img src".$this->icon."/>";
      
    // Define los campos a utilizar en el formulario de configuración
    $this->init_form_fields();
    // Carga de Variables
    $this->init_settings();
    // Se agregan las acciónes a los plugins
    $this->init_actions();
      
    // Proceso para convertir las configuraciones a variables.
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }

    $this->apply_environment_credentials();
  } 

  /**
  * Define las credenciales activas segun el entorno seleccionado.
  */
  private function apply_environment_credentials() {
    $environment = ! empty( $this->environment ) ? $this->environment : 'sandbox';

    if ( 'live' === $environment ) {
      $this->secret_key = ! empty( $this->live_secret_key ) ? $this->live_secret_key : $this->secret_key;
      return;
    }

    $this->secret_key = ! empty( $this->sandbox_secret_key ) ? $this->sandbox_secret_key : $this->secret_key;
  }

  /**
  * Devuelve el estado configurado sin prefijo wc-.
  *
  * @return string
  */
  private function get_configured_order_status_slug() {
    $configured_status = ! empty( $this->order_status ) ? $this->order_status : 'wc-completed';
    return str_replace( 'wc-', '', $configured_status );
  }

  /**
  * Función para patron de singleton
  * 
  * @author Mimer
  * @return EpicPay Clase inicializada
  * @since 1.2.0
  */ 
  public static function get_instance() {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
  * Función que inicializa las acciones
  * 
  * @author Mimer
  * @since 1.2.0
  */ 
  public function init_actions(){
    add_action( 'admin_notices', array( $this,  'validate_activation' ) );
    add_action( 'woocommerce_api_epicpay', array( $this, 'redirect_callback' ) );
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_visibility_script' ) );
    }  
  }  

  /**
  * Muestra/Oculta campos de llaves segun el entorno seleccionado.
  */
  public function enqueue_settings_visibility_script() {
    if ( ! isset( $_GET['page'], $_GET['section'] ) ) {
      return;
    }

    $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
    $section = sanitize_text_field( wp_unslash( $_GET['section'] ) );

    if ( 'wc-settings' !== $page || $this->id !== $section ) {
      return;
    }

    $script = "jQuery(function($){
      var keyFields = [
        '#woocommerce_epicpay_sandbox_secret_key',
        '#woocommerce_epicpay_live_secret_key',
        '#woocommerce_epicpay_secret_key'
      ];

      function maskEpicPayValue(value){
        if (!value) {
          return '';
        }

        if (value.length <= 8) {
          return value;
        }

        return value.substring(0, 4) + '********' + value.substring(value.length - 4);
      }

      function initializeMaskedField(selector){
        var field = $(selector);
        if (!field.length) {
          return;
        }

        var rawValue = field.val();
        field.attr('data-epicpay-full-value', rawValue);

        if (rawValue) {
          field.val(maskEpicPayValue(rawValue));
        }

        field.on('focus', function(){
          $(this).val($(this).attr('data-epicpay-full-value') || '');
        });

        field.on('input', function(){
          $(this).attr('data-epicpay-full-value', $(this).val());
        });

        field.on('blur', function(){
          var latestValue = $(this).val();
          $(this).attr('data-epicpay-full-value', latestValue);
          $(this).val(maskEpicPayValue(latestValue));
        });
      }

      function toggleEpicPayFields(){
        var env = $('#woocommerce_epicpay_environment').val();
        var sandboxFields = [
          '#woocommerce_epicpay_sandbox_secret_key'
        ];
        var liveFields = [
          '#woocommerce_epicpay_live_secret_key'
        ];
        var legacyFields = [
          '#woocommerce_epicpay_secret_key'
        ];

        sandboxFields.forEach(function(selector){
          $(selector).closest('tr').toggle(env === 'sandbox');
        });

        liveFields.forEach(function(selector){
          $(selector).closest('tr').toggle(env === 'live');
        });

        legacyFields.forEach(function(selector){
          $(selector).closest('tr').hide();
        });
      }

      keyFields.forEach(initializeMaskedField);
      $('#woocommerce_epicpay_environment').on('change', toggleEpicPayFields);
      $('#mainform').on('submit', function(){
        keyFields.forEach(function(selector){
          var field = $(selector);
          if (field.length) {
            field.val(field.attr('data-epicpay-full-value') || '');
          }
        });
      });
      toggleEpicPayFields();
    });";

    wp_add_inline_script( 'jquery', $script );
  }

  /**
  * Función encargada de inicializar el formulario de configuración del pugin
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function init_form_fields() {
    include_once dirname(__FILE__) . '/../includes/recurrente-settings.php';
    $this->form_fields = EpicPaySettings::get_settings();
  }

  /**
  * Función encargada del manejo de Callbacks por parte de la pasarela de pago
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function redirect_callback(){
    if ( isset( $_GET['status'] ) ) {
      $this->answer_redirect(); //Esto quiere decir que es el redirect URL del checkout
    } else {
      $this->process_webhook(); //Esto quiere decir que es el Webhook
    }
  }

  /**
  * Función encargada del manejo de la redirección
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function answer_redirect(){
    $status_id = isset( $_GET['status'] ) ? absint( wp_unslash( $_GET['status'] ) ) : -1;
    $order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;

    if ( ! $order_id ) {
      wp_die( esc_html__( 'Orden invalida.', 'epicpay' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
      wp_die( esc_html__( 'Orden no encontrada.', 'epicpay' ) );
    }

    if ( 1 === $status_id ) {
      $redirect_url = $order->get_checkout_order_received_url();
      if ( $order->has_status( array( 'pending', 'failed', 'on-hold' ) ) ) {
        $order->payment_complete();

        $configured_status = $this->get_configured_order_status_slug();
        if ( $order->get_status() !== $configured_status ) {
          $order->update_status( $configured_status );
        }
      }
      $order->add_order_note( 'EpicPay: La transaccion fue completada por el usuario.' );
      wp_safe_redirect( $redirect_url );
      exit;
    } elseif ( 0 === $status_id ) {
      $checkout_url = add_query_arg( [
        'cancel' => 'true',
      ], wc_get_checkout_url() );
      if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
        $order->update_status( 'cancelled' );
      }
      $order->add_order_note( 'EpicPay: La transaccion fue cancelada por el usuario.' );
      wp_safe_redirect( $checkout_url );
      exit;
    }

    wp_die( esc_html__( 'Estado de pago invalido.', 'epicpay' ) );
  }

  /**
  * Función encargada de procesar las respuesta del WebHook.
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function process_webhook(){
    $jsonData = file_get_contents( 'php://input' );
    $data = json_decode( $jsonData );

    if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data->event_type ) ) {
      status_header( 400 );
      wp_die( esc_html__( 'Payload de webhook invalido.', 'epicpay' ) );
    }

    $event = sanitize_text_field( (string) $data->event_type );
    
    include_once dirname(__FILE__) . '/../includes/recurrente-response.php';
    $response = new EpicPayResponse( $event );
    $response->execute( $data );

    status_header( 200 );
    exit;
  }

  /**
  * Función encargada de procesar el pago de WooCommerce
  * 
  * @author Mimer
  * @return Array Arreglo que contiene el resultado del proceso de la transacción y el URL para redirigir
  * @since 2.0.0
  */
  public function process_payment( $order_id ) {
    include_once 'single-checkout.php';

    $customer_order = new WC_Order( $order_id ); //Crear Orden de WooCommerce
      
    $single_checkout = new Single_Checkout($customer_order); //Inicia un checkout simpre 
    $checkout_transaction = $single_checkout->create(); 

    if ( is_wp_error( $checkout_transaction ) ) {
      wc_add_notice( $checkout_transaction->get_error_message(), 'error' );
      return array( 'result' => 'failure' );
    }
    if ( 201 !== (int) $single_checkout->code || empty( $single_checkout->url ) ) {
      wc_add_notice( __( 'No se pudo iniciar el checkout con EpicPay.', 'epicpay' ), 'error' );
      return array( 'result' => 'failure' );
    }

    $customer_order->add_order_note( 'EpicPay: Se inicializo el proceso de pago.' );
    $customer_order->update_meta_data( 'epicpay_checkout_id', $single_checkout->id );
    $customer_order->update_meta_data( 'epicpay_checkout_url', $single_checkout->url );
    $customer_order->update_meta_data( 'epicpay_product_id', $single_checkout->product );
    $customer_order->save();

    return array(
      'result'   => 'success',
      'redirect' => $single_checkout->url,
    );
  }
  
  /**
  * Función encargada de validar los campos de la configuración.
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function validate_fields() {
    return true;
  }

  /**
  * Función encargada de mostrar el mensaje de error al usuario.
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function fail($message){
    throw new Exception( __( $message, 'epicpay' ) );
  }

  /**
  * Función encargada de validar la correcta activación del plugin.
  * 
  * @author Mimer
  * @since 1.2.0
  */
  public function validate_activation(){
    if( $this->enabled == "yes" ) {
      if ( empty( $this->secret_key ) ) {
        $current_environment = ! empty( $this->environment ) ? $this->environment : 'sandbox';
        $environment_label = 'live' === $current_environment ? __( 'Live', 'epicpay' ) : __( 'Sandbox', 'epicpay' );
        echo "<div class=\"error\"><p>" . sprintf( __( '<strong>%s</strong> No tienes configurado correctamente el plugin, <a href="%s">por favor dirigete a la configuracion.</a>', 'epicpay' ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout&section=epicpay' ) ) . "</p></div>";
        echo "<div class=\"error\"><p>" . sprintf( __( 'EpicPay: falta la llave secreta para el entorno %s.', 'epicpay' ), esc_html( $environment_label ) ) . "</p></div>";
      }
    }   
  }
}