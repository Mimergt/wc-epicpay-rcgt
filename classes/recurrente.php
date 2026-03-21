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
      'tokenization',
      'add_payment_method',
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
    if ( isset( $_GET['tokenize'] ) ) {
      $this->answer_tokenization_redirect();
    } elseif ( isset( $_GET['status'] ) ) {
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
  * Flujo de WooCommerce "Añadir método de pago" (My Account).
  * Crea un checkout de tokenización en Recurrente con monto 0.
  *
  * @return array
  */
  public function add_payment_method() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
      wc_add_notice( __( 'Debes iniciar sesión para guardar una tarjeta.', 'epicpay' ), 'error' );
      return array( 'result' => 'failure' );
    }

    $credentials_error = $this->validate_active_credentials();
    if ( is_wp_error( $credentials_error ) ) {
      $this->log_message( 'error', 'EpicPay add_payment_method credentials error: ' . $credentials_error->get_error_message() );
      wc_add_notice( $credentials_error->get_error_message(), 'error' );
      return array( 'result' => 'failure' );
    }

    $url = trailingslashit( $this->get_api_base_url() ) . 'checkouts';
    $payload = $this->get_tokenization_checkout_payload( $user_id );

    $response = wp_remote_post(
      $url,
      array(
        'headers' => $this->get_api_headers(),
        'body' => wp_json_encode( $payload ),
        'timeout' => 30,
      )
    );

    if ( is_wp_error( $response ) ) {
      $this->log_message( 'error', 'EpicPay tokenization checkout request failed: ' . $response->get_error_message() );
      wc_add_notice( __( 'No se pudo iniciar el guardado de tarjeta.', 'epicpay' ), 'error' );
      return array( 'result' => 'failure' );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body_raw = wp_remote_retrieve_body( $response );
    $body = json_decode( $body_raw, true );

    if ( 201 !== $code ) {
      $message = isset( $body['error'] ) ? $body['error'] : ( isset( $body['message'] ) ? $body['message'] : __( 'Error al iniciar tokenización.', 'epicpay' ) );
      $this->log_message( 'error', 'EpicPay tokenization checkout API error HTTP ' . $code . ': ' . $message . ' | response=' . $body_raw );
      wc_add_notice( $message, 'error' );
      return array( 'result' => 'failure' );
    }

    $checkout_id = isset( $body['id'] ) ? $body['id'] : '';
    $checkout_url = isset( $body['checkout_url'] ) ? $body['checkout_url'] : ( isset( $body['url'] ) ? $body['url'] : '' );

    if ( empty( $checkout_id ) || empty( $checkout_url ) ) {
      $this->log_message( 'error', 'EpicPay tokenization checkout missing id/url. response=' . $body_raw );
      wc_add_notice( __( 'Recurrente no devolvió datos de checkout para tokenización.', 'epicpay' ), 'error' );
      return array( 'result' => 'failure' );
    }

    update_user_meta( $user_id, 'epicpay_pending_tokenization_checkout_id', $checkout_id );
    $this->log_message( 'info', 'EpicPay tokenization checkout created: user=' . $user_id . ' checkout=' . $checkout_id );

    return array(
      'result' => 'success',
      'redirect' => $checkout_url,
    );
  }

  /**
  * Callback para retorno de tokenización.
  * Guarda el payment_method como token de WooCommerce.
  *
  * @return void
  */
  public function answer_tokenization_redirect() {
    $status = isset( $_GET['status'] ) ? absint( wp_unslash( $_GET['status'] ) ) : -1;
    $user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : get_current_user_id();

    if ( ! $user_id ) {
      wc_add_notice( __( 'No se pudo identificar el usuario para guardar la tarjeta.', 'epicpay' ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $pending_checkout_id = (string) get_user_meta( $user_id, 'epicpay_pending_tokenization_checkout_id', true );

    if ( 1 !== $status ) {
      delete_user_meta( $user_id, 'epicpay_pending_tokenization_checkout_id' );
      wc_add_notice( __( 'Guardado de tarjeta cancelado por el usuario.', 'epicpay' ), 'notice' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    if ( empty( $pending_checkout_id ) ) {
      wc_add_notice( __( 'No se encontró una tokenización pendiente.', 'epicpay' ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $checkout = $this->get_checkout_by_id( $pending_checkout_id );
    if ( is_wp_error( $checkout ) ) {
      $this->log_message( 'error', 'EpicPay tokenization checkout fetch failed: ' . $checkout->get_error_message() );
      wc_add_notice( __( 'No se pudo confirmar la tarjeta guardada. Intenta de nuevo.', 'epicpay' ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $payment_method_data = $this->extract_payment_method_data( $checkout );
    if ( empty( $payment_method_data['id'] ) ) {
      $this->log_message( 'error', 'EpicPay tokenization missing payment_method_id for checkout=' . $pending_checkout_id );
      wc_add_notice( __( 'No se recibió el identificador del método de pago desde Recurrente.', 'epicpay' ), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    $save_result = $this->persist_user_payment_token( $user_id, $payment_method_data );
    if ( is_wp_error( $save_result ) ) {
      $this->log_message( 'error', 'EpicPay token save failed: ' . $save_result->get_error_message() );
      wc_add_notice( $save_result->get_error_message(), 'error' );
      wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
      exit;
    }

    delete_user_meta( $user_id, 'epicpay_pending_tokenization_checkout_id' );
    wc_add_notice( __( 'Tarjeta guardada exitosamente.', 'epicpay' ), 'success' );
    wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
    exit;
  }

  /**
  * Construye payload de checkout para tokenización de tarjeta.
  *
  * @param int $user_id Usuario WP.
  * @return array
  */
  private function get_tokenization_checkout_payload( $user_id ) {
    $currency = get_woocommerce_currency();
    $success_url = add_query_arg(
      array(
        'wc-api' => 'epicpay',
        'tokenize' => 1,
        'status' => 1,
        'user_id' => (int) $user_id,
      ),
      home_url( '/' )
    );

    $cancel_url = add_query_arg(
      array(
        'wc-api' => 'epicpay',
        'tokenize' => 1,
        'status' => 0,
        'user_id' => (int) $user_id,
      ),
      home_url( '/' )
    );

    return array(
      'items' => array(
        array(
          'name' => __( 'Guardar método de pago', 'epicpay' ),
          'currency' => $currency,
          'amount_in_cents' => 0,
          'charge_type' => 'one_time',
          'quantity' => 1,
        ),
      ),
      'user_id' => (string) $user_id,
      'success_url' => $success_url,
      'cancel_url' => $cancel_url,
      'metadata' => array(
        'source' => 'woocommerce_add_payment_method',
        'wp_user_id' => (string) $user_id,
      ),
    );
  }

  /**
  * Consulta checkout por id en Recurrente.
  *
  * @param string $checkout_id ID checkout.
  * @return array|WP_Error
  */
  private function get_checkout_by_id( $checkout_id ) {
    $url = trailingslashit( $this->get_api_base_url() ) . 'checkouts/' . rawurlencode( $checkout_id );
    $response = wp_remote_get(
      $url,
      array(
        'headers' => $this->get_api_headers(),
        'timeout' => 30,
      )
    );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body_raw = wp_remote_retrieve_body( $response );
    $body = json_decode( $body_raw, true );

    if ( 200 !== $code || ! is_array( $body ) ) {
      $message = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : __( 'Respuesta inválida al consultar checkout.', 'epicpay' );
      return new WP_Error( 'epicpay_checkout_fetch_failed', $message );
    }

    return $body;
  }

  /**
  * Extrae payment_method desde respuesta de checkout.
  *
  * @param array $checkout Checkout response.
  * @return array
  */
  private function extract_payment_method_data( $checkout ) {
    $payment_method = array();

    if ( isset( $checkout['payment_method'] ) && is_array( $checkout['payment_method'] ) ) {
      $payment_method = $checkout['payment_method'];
    } elseif ( isset( $checkout['setup_intent']['payment_method'] ) && is_array( $checkout['setup_intent']['payment_method'] ) ) {
      $payment_method = $checkout['setup_intent']['payment_method'];
    }

    if ( empty( $payment_method['id'] ) && isset( $checkout['payment_method_id'] ) ) {
      $payment_method['id'] = $checkout['payment_method_id'];
    }

    $card = isset( $payment_method['card'] ) && is_array( $payment_method['card'] ) ? $payment_method['card'] : array();

    return array(
      'id' => isset( $payment_method['id'] ) ? (string) $payment_method['id'] : '',
      'last4' => isset( $card['last4'] ) ? (string) $card['last4'] : '0000',
      'brand' => isset( $card['network'] ) ? (string) $card['network'] : 'card',
      'exp_month' => isset( $card['exp_month'] ) ? (int) $card['exp_month'] : 12,
      'exp_year' => isset( $card['exp_year'] ) ? (int) $card['exp_year'] : ( (int) gmdate( 'Y' ) + 5 ),
    );
  }

  /**
  * Guarda el método de pago tokenizado en WooCommerce.
  *
  * @param int $user_id Usuario WP.
  * @param array $payment_method_data Datos del payment_method.
  * @return int|WP_Error
  */
  private function persist_user_payment_token( $user_id, $payment_method_data ) {
    if ( ! class_exists( 'WC_Payment_Token_CC' ) ) {
      return new WP_Error( 'epicpay_wc_token_unavailable', __( 'WooCommerce Payment Token API no está disponible.', 'epicpay' ) );
    }

    $token_value = isset( $payment_method_data['id'] ) ? (string) $payment_method_data['id'] : '';
    if ( '' === $token_value ) {
      return new WP_Error( 'epicpay_token_empty', __( 'No se recibió token de método de pago.', 'epicpay' ) );
    }

    $existing_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $this->id );
    foreach ( $existing_tokens as $existing_token ) {
      if ( $existing_token->get_token() === $token_value ) {
        $existing_token->set_default( true );
        $existing_token->save();
        return (int) $existing_token->get_id();
      }
    }

    $token = new WC_Payment_Token_CC();
    $token->set_gateway_id( $this->id );
    $token->set_token( $token_value );
    $token->set_user_id( $user_id );
    $token->set_last4( isset( $payment_method_data['last4'] ) ? $payment_method_data['last4'] : '0000' );
    $token->set_card_type( isset( $payment_method_data['brand'] ) ? strtolower( $payment_method_data['brand'] ) : 'card' );
    $token->set_expiry_month( max( 1, min( 12, (int) $payment_method_data['exp_month'] ) ) );
    $token->set_expiry_year( max( (int) gmdate( 'Y' ), (int) $payment_method_data['exp_year'] ) );
    $token->set_default( true );

    $token_id = $token->save();
    if ( ! $token_id ) {
      return new WP_Error( 'epicpay_token_save_failed', __( 'No se pudo guardar la tarjeta en WooCommerce.', 'epicpay' ) );
    }

    return (int) $token_id;
  }

  /**
  * URL base API Recurrente.
  *
  * @return string
  */
  private function get_api_base_url() {
    $default_base_url = 'https://app.recurrente.com/api';
    return untrailingslashit( apply_filters( 'epicpay_api_base_url', $default_base_url ) );
  }

  /**
  * Headers API Recurrente.
  *
  * @return array
  */
  private function get_api_headers() {
    return array(
      'X-PUBLIC-KEY' => trim( (string) $this->public_key ),
      'X-SECRET-KEY' => trim( (string) $this->secret_key ),
      'X-ORIGIN' => site_url(),
      'X-STORE' => get_bloginfo( 'name' ),
      'Content-Type' => 'application/json',
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