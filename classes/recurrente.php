<?php
/**
* Clase principal para interactuar con el API de EpicPay (Recurrente).
*/
class EpicPay extends WC_Payment_Gateway {
  public $environment;
  public $public_key;    
  public $secret_key;
  public $enable_subscriptions;
  public $sandbox_public_key;
  public $sandbox_secret_key;
  public $live_public_key;
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
    
    // Define subscription support
    $this->supports = array(
      'products',
      'subscriptions',
      'subscription_cancellation',
      'subscription_suspension',
      'subscription_reactivation',
      'subscription_amount_changes',
      'subscription_date_changes',
      'subscription_payment_method_change',
      'subscription_payment_method_change_customer',
      'subscription_payment_method_change_admin',
    );
      
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
      $this->public_key = ! empty( $this->live_public_key ) ? trim( $this->live_public_key ) : trim( (string) $this->public_key );
      $this->secret_key = ! empty( $this->live_secret_key ) ? trim( $this->live_secret_key ) : trim( (string) $this->secret_key );
      return;
    }

    $this->public_key = ! empty( $this->sandbox_public_key ) ? trim( $this->sandbox_public_key ) : trim( (string) $this->public_key );
    $this->secret_key = ! empty( $this->sandbox_secret_key ) ? trim( $this->sandbox_secret_key ) : trim( (string) $this->secret_key );
  }

  /**
  * Determina si el soporte de suscripciones está habilitado desde settings.
  *
  * @return bool
  */
  private function is_subscriptions_enabled() {
    return ! isset( $this->enable_subscriptions ) || 'yes' === $this->enable_subscriptions;
  }

  /**
  * Lista de features relacionadas a WooCommerce Subscriptions.
  *
  * @return array
  */
  private function get_subscription_supports() {
    return array(
      'subscriptions',
      'subscription_cancellation',
      'subscription_suspension',
      'subscription_reactivation',
      'subscription_amount_changes',
      'subscription_date_changes',
      'subscription_payment_method_change',
      'subscription_payment_method_change_customer',
      'subscription_payment_method_change_admin',
    );
  }

  /**
  * Detecta si una orden contiene suscripciones.
  *
  * @param int $order_id ID de la orden.
  * @return bool
  */
  private function order_contains_subscription( $order_id ) {
    if ( function_exists( 'wcs_order_contains_subscription' ) ) {
      return (bool) wcs_order_contains_subscription( $order_id );
    }

    if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
      $subscriptions = wcs_get_subscriptions_for_order( $order_id );
      return ! empty( $subscriptions );
    }

    return false;
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
  * Devuelve una versión enmascarada de una llave para logs.
  *
  * @param string $key Llave a enmascarar.
  * @return string
  */
  private function mask_key_preview( $key ) {
    $key = trim( (string) $key );
    if ( '' === $key ) {
      return 'empty';
    }

    $start = substr( $key, 0, 8 );
    $end = strlen( $key ) > 4 ? substr( $key, -4 ) : $key;
    return $start . '...' . $end;
  }

  /**
  * Logger centralizado para WooCommerce logs con source epicpay.
  *
  * @param string $level Nivel de log (debug|info|warning|error).
  * @param string $message Mensaje.
  * @return void
  */
  private function log_message( $level, $message ) {
    if ( function_exists( 'wc_get_logger' ) ) {
      $logger = wc_get_logger();
      $logger->log( $level, $message, array( 'source' => 'epicpay' ) );
      return;
    }

    error_log( 'EpicPay [' . strtoupper( $level ) . '] ' . $message );
  }

  /**
  * Valida llaves activas antes de ejecutar checkout.
  *
  * @return WP_Error|null
  */
  private function validate_active_credentials() {
    $public_key = trim( (string) $this->public_key );
    $secret_key = trim( (string) $this->secret_key );
    $environment = ! empty( $this->environment ) ? $this->environment : 'sandbox';

    if ( '' === $public_key || '' === $secret_key ) {
      return new WP_Error( 'epicpay_missing_credentials', __( 'Faltan llaves API activas para el entorno seleccionado.', 'epicpay' ) );
    }

    // Si quedó guardado un valor enmascarado, la API responderá 401/400.
    if ( false !== strpos( $public_key, '*' ) || false !== strpos( $secret_key, '*' ) ) {
      return new WP_Error( 'epicpay_masked_credentials', __( 'Las llaves guardadas parecen estar enmascaradas. Reingresalas completas y guarda nuevamente.', 'epicpay' ) );
    }

    if ( 0 !== strpos( $public_key, 'pk_' ) || 0 !== strpos( $secret_key, 'sk_' ) ) {
      return new WP_Error( 'epicpay_invalid_credentials_prefix', __( 'Formato de llaves inválido. Verifica que inicien con pk_ y sk_.', 'epicpay' ) );
    }

    if ( 'sandbox' === $environment && ( 0 !== strpos( $public_key, 'pk_test_' ) || 0 !== strpos( $secret_key, 'sk_test_' ) ) ) {
      return new WP_Error( 'epicpay_env_credentials_mismatch', __( 'Estás en Sandbox pero las llaves activas no son de prueba (test).', 'epicpay' ) );
    }

    if ( 'live' === $environment && ( 0 !== strpos( $public_key, 'pk_live_' ) || 0 !== strpos( $secret_key, 'sk_live_' ) ) ) {
      return new WP_Error( 'epicpay_env_credentials_mismatch', __( 'Estás en Live pero las llaves activas no son de producción (live).', 'epicpay' ) );
    }

    return null;
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
  * Verifica si el gateway soporta una característica específica
  * Required by WooCommerce Subscriptions to detect subscription support
  * 
  * @param string $feature Característica a verificar (ej: 'subscriptions')
  * @return bool
  * @since 2.0.1
  */
  public function supports( $feature ) {
    if ( in_array( $feature, $this->get_subscription_supports(), true ) && ! $this->is_subscriptions_enabled() ) {
      return false;
    }

    // Validar que el gateway esté activo y tenga credenciales para suscripciones
    if ( 'subscriptions' === $feature ) {
      // Verificar que WC Subscriptions esté activo
      if ( ! function_exists( 'wcs_order_contains_subscription' ) && ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
        error_log( 'EpicPay: WC Subscriptions not active' );
        return false;
      }
      
      // Verificar que tengamos credenciales configuradas
      if ( empty( $this->public_key ) || empty( $this->secret_key ) ) {
        error_log( 'EpicPay: Subscriptions support disabled - missing credentials' );
        return false;
      }
      
      // Verificar que el gateway esté habilitado
      if ( 'yes' !== $this->enabled ) {
        error_log( 'EpicPay: Subscriptions support disabled - gateway not enabled' );
        return false;
      }
    }
    
    // Usar el array de soporte definido en el constructor
    return parent::supports( $feature );
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
    
    // Agregar hook para pagos recurrentes programados por WC
    add_action( 'woocommerce_scheduled_subscription_payment_epicpay', array( $this, 'scheduled_subscription_payment' ), 10, 2 );
    
    // Hooks para cambios de estado de suscripción
    add_action( 'woocommerce_subscription_cancelled_epicpay', array( $this, 'on_subscription_cancelled' ), 10, 1 );
    add_action( 'woocommerce_subscription_suspended_epicpay', array( $this, 'on_subscription_suspended' ), 10, 1 );
    add_action( 'woocommerce_subscription_reactivated_epicpay', array( $this, 'on_subscription_reactivated' ), 10, 1 );
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
      function toggleEpicPayFields(){
        var env = $('#woocommerce_epicpay_environment').val();
        var sandboxFields = [
          '#woocommerce_epicpay_sandbox_public_key',
          '#woocommerce_epicpay_sandbox_secret_key'
        ];
        var liveFields = [
          '#woocommerce_epicpay_live_public_key',
          '#woocommerce_epicpay_live_secret_key'
        ];
        var legacyFields = [
          '#woocommerce_epicpay_public_key',
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

      $('#woocommerce_epicpay_environment').on('change', toggleEpicPayFields);
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
    $customer_order = new WC_Order( $order_id );
    $is_subscription_order = $this->order_contains_subscription( $order_id );

    $this->log_message(
      'info',
      sprintf(
        'EpicPay process_payment: order=%d env=%s subscription_order=%s pk=%s sk=%s',
        (int) $order_id,
        ! empty( $this->environment ) ? $this->environment : 'sandbox',
        $is_subscription_order ? 'yes' : 'no',
        $this->mask_key_preview( $this->public_key ),
        $this->mask_key_preview( $this->secret_key )
      )
    );

    $credentials_error = $this->validate_active_credentials();
    if ( is_wp_error( $credentials_error ) ) {
      $this->log_message( 'error', 'EpicPay credentials validation failed: ' . $credentials_error->get_error_message() );
      wc_add_notice( $credentials_error->get_error_message(), 'error' );
      return array( 'result' => 'failure' );
    }
    
    // Detectar si es una suscripción
    // Primero verificar que WC_Subscriptions está disponible
    if ( $is_subscription_order ) {
      if ( ! $this->is_subscriptions_enabled() ) {
        wc_add_notice( __( 'El soporte de suscripciones de EpicPay está desactivado en la configuración.', 'epicpay' ), 'error' );
        return array( 'result' => 'failure' );
      }

      // Validar que WC_Subscriptions_Order existe
      if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
        wc_add_notice( 
          __( 'WooCommerce Subscriptions debe estar instalado y activo para procesar suscripciones.', 'epicpay' ), 
          'error' 
        );
        return array( 'result' => 'failure' );
      }

      include_once 'subscription-checkout.php';
      $checkout = new Subscription_Checkout( $customer_order );
    } else {
      include_once 'single-checkout.php';
      $checkout = new Single_Checkout( $customer_order );
    }
    
    $checkout_transaction = $checkout->create();

    if ( is_wp_error( $checkout_transaction ) ) {
      $this->log_message( 'error', 'EpicPay process_payment WP_Error: ' . $checkout_transaction->get_error_message() );
      wc_add_notice( $checkout_transaction->get_error_message(), 'error' );
      return array( 'result' => 'failure' );
    }
    if ( 201 !== (int) $checkout->code || empty( $checkout->url ) ) {
      wc_add_notice( __( 'No se pudo iniciar el checkout con EpicPay.', 'epicpay' ), 'error' );
      return array( 'result' => 'failure' );
    }

    $note = $is_subscription_order
      ? 'EpicPay: Se inicializó suscripción.'
      : 'EpicPay: Se inicializo el proceso de pago.';

    $customer_order->add_order_note( $note );
    $customer_order->update_meta_data( 'epicpay_checkout_id', $checkout->id );
    $customer_order->update_meta_data( 'epicpay_checkout_url', $checkout->url );
    $customer_order->update_meta_data( 'epicpay_product_id', $checkout->product );
    $customer_order->save();

    return array(
      'result'   => 'success',
      'redirect' => $checkout->url,
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
      if ( empty( $this->secret_key ) || empty( $this->public_key ) ) {
        $current_environment = ! empty( $this->environment ) ? $this->environment : 'sandbox';
        $environment_label = 'live' === $current_environment ? __( 'Live', 'epicpay' ) : __( 'Sandbox', 'epicpay' );
        echo "<div class=\"error\"><p>" . sprintf( __( '<strong>%s</strong> No tienes configurado correctamente el plugin, <a href="%s">por favor dirigete a la configuracion.</a>', 'epicpay' ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout&section=epicpay' ) ) . "</p></div>";
        echo "<div class=\"error\"><p>" . sprintf( __( 'EpicPay: faltan llaves para el entorno %s.', 'epicpay' ), esc_html( $environment_label ) ) . "</p></div>";
      }
    }   
  }

  /**
   * Procesa pago de renovación programada por WooCommerce
   * Se ejecuta cuando WC Subscriptions programa una renovación
   * 
   * @param float $amount_to_charge Monto a cobrar
   * @param WC_Order $renewal_order Orden de renovación
   */
  public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
    // Crear checkout para renovación
    include_once 'single-checkout.php';
    
    $checkout = new Single_Checkout( $renewal_order );
    $checkout_result = $checkout->create();
    
    if ( is_wp_error( $checkout_result ) ) {
      WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order );
      return;
    }
    
    // Guardar referencia de checkout
    $renewal_order->update_meta_data( 'epicpay_renewal_checkout_id', $checkout->id );
    $renewal_order->add_order_note( 'EpicPay: Checkout de renovación creado.' );
    $renewal_order->save();
    
    // El webhook manejará el resultado cuando regrese del pago
  }
  
  /**
   * Maneja cancelación de suscripción
   * 
   * @param WC_Subscription $subscription
   */
  public function on_subscription_cancelled( $subscription ) {
    $subscription->add_order_note( 'Suscripción cancelada en EpicPay.' );
  }
  
  /**
   * Maneja suspensión de suscripción
   * 
   * @param WC_Subscription $subscription
   */
  public function on_subscription_suspended( $subscription ) {
    $subscription->add_order_note( 'Suscripción suspendida en EpicPay.' );
  }
  
  /**
   * Maneja reactivación de suscripción
   * 
   * @param WC_Subscription $subscription
   */
  public function on_subscription_reactivated( $subscription ) {
    $subscription->add_order_note( 'Suscripción reactivada en EpicPay.' );
  }
}