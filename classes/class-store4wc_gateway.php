<?php
/**
 * Stripe Gateway
 *
 * Provides a custom Stripe Payment Gateway.
 *
 * @class       Store4WC_Gateway
 * @extends     WC_Payment_Gateway
 * @package     WooCommerce/Classes/Payment
 * @author      eVersatile
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Store4WC_Gateway extends WC_Payment_Gateway {
    protected $order                     = null;
    protected $form_data                 = null;
    protected $transaction_id            = null;
    protected $transaction_error_message = null;

    public function __construct() {

        $this->id           = 'store4';
        $this->method_title = 'Store 4';
        $this->has_fields   = true;
        $this->supports     = array(
            'default_credit_card_form',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'refunds'
        );

        // Init settings
        $this->init_form_fields();
        $this->init_settings();

        // Use settings
        $this->enabled     = $this->settings['enabled'];
        $this->title       = $this->settings['title'];
        $this->description = $this->settings['description'];

/**
 * Check current logged in user, conditionally load gateway
 *
 * @param $methods
 * @return array
 */
function add_store4wc_gateway( $methods ) {
  if( get_current_user_id() === 1 ){
    $methods[] = 'Store4WC_Gateway';
    return $methods;
  }
}

add_filter( 'woocommerce_payment_gateways', 'add_store4wc_gateway' );

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'woocommerce_credit_card_form_start', array( $this, 'before_cc_form' ) );
        add_action( 'woocommerce_credit_card_form_end', array( $this, 'after_cc_form' ) );
    }
        /**
     * Check if this gateway is enabled and all dependencies are fine.
     * Disable the plugin if dependencies fail.
     *
     * @access      public
     * @return      bool
     */
    public function is_available() {
        global $s4wc;

        if ( $this->enabled === 'no' ) {
            return false;
        }

        // Stripe won't work without keys
        if ( ! $s4wc->settings['publishable_key'] && ! $s4wc->settings['secret_key'] ) {
            return false;
        }

         /**
     * Initialise Gateway Settings Form Fields
     *
     * @access      public
     * @return      void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Enable/Disable', 'store4' ),
                'label'         => __( 'Enable Store4', 'store4' ),
                'default'       => 'yes'
            ),
            'title' => array(
                'type'          => 'text',
                'title'         => __( 'Title', 'store4' ),
                'description'   => __( 'This controls the title which the user sees during checkout.', 'store4' ),
                'default'       => __( 'Credit Card Payment', 'store4' )
            ),
            'description' => array(
                'type'          => 'textarea',
                'title'         => __( 'Description', 'store4' ),
                'description'   => __( 'This controls the description which the user sees during checkout.', 'store4' ),
                'default'       => '',
            ),
            'charge_type' => array(
                'type'          => 'select',
                'title'         => __( 'Charge Type', 'store4' ),
                'description'   => __( 'Choose to capture payment at checkout, or authorize only to capture later.', 'store4' ),
                'options'       => array(
                    'capture'   => __( 'Authorize & Capture', 'store4' ),
                    'authorize' => __( 'Authorize Only', 'store4' )
                ),
                'default'       => 'capture'
            ),
            'additional_fields' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Additional Fields', 'store4' ),
                'description'   => __( 'Add a Billing ZIP and a Name on Card for Stripe authentication purposes. This is only neccessary if you check the "Only ship to the users billing address" box on WooCommerce Shipping settings.', 'store4' ),
                'label'         => __( 'Use Additional Fields', 'store4' ),
                'default'       => 'no'
            ),
            'saved_cards' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Saved Cards', 'store4' ),
                'description'   => __( 'Allow customers to use saved cards for future purchases.', 'store4' ),
                'default'       => 'yes',
            ),
            'testmode' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Test Mode', 'store4' ),
                'description'   => __( 'Use the test mode on Stripe\'s dashboard to verify everything works before going live.', 'store4' ),
                'label'         => __( 'Turn on testing', 'store4' ),
                'default'       => 'no'
            ),
            'test_secret_key'   => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Test Secret key', 'store4' ),
                'default'       => '',
            ),
            'test_publishable_key' => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Test Publishable key', 'store4' ),
                'default'       => '',
            ),
            'live_secret_key'   => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Live Secret key', 'store4' ),
                'default'       => '',
            ),
            'live_publishable_key' => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Live Publishable key', 'store4' ),
                'default'       => '',
            ),
        );
    }

   /**
     * Load dependent scripts
     * - stripe.js from the stripe servers
     * - s4wc.js for handling the data to submit to stripe
     *
     * @access      public
     * @return      void
     */
    public function load_scripts() {
        global $s4wc;

        // Main stripe js
        wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', false, '2.0', true );

        // Plugin js
        wp_enqueue_script( 's4wc_js', plugins_url( 'assets/js/s4wc.min.js', dirname( __FILE__ ) ), array( 'stripe', 'wc-credit-card-form' ), '1.36', true );

        // Add data that s4wc.js needs
        $s4wc_info = array(
            'publishableKey'    => $s4wc->settings['publishable_key'],
            'savedCardsEnabled' => $s4wc->settings['saved_cards'] === 'yes' ? true : false,
            'hasCard'           => ( $this->stripe_customer_info && count( $this->stripe_customer_info['cards'] ) ) ? true : false
        );

        // If we're on the pay page, Stripe needs the address
        if ( is_checkout_pay_page() ) {
            $order_key = urldecode( $_GET['key'] );
            $order_id  = absint( get_query_var( 'order-pay' ) );
            $order     = new WC_Order( $order_id );

            if ( $order->id == $order_id && $order->order_key == $order_key ) {
                $s4wc_info['billing_name']      = $order->billing_first_name . ' ' . $order->billing_last_name;
                $s4wc_info['billing_address_1'] = $order->billing_address_1;
                $s4wc_info['billing_address_2'] = $order->billing_address_2;
                $s4wc_info['billing_city']      = $order->billing_city;
                $s4wc_info['billing_state']     = $order->billing_state;
                $s4wc_info['billing_postcode']  = $order->billing_postcode;
                $s4wc_info['billing_country']   = $order->billing_country;
            }
        }

        wp_localize_script( 's4wc_js', 's4wc_info', $s4wc_info );
    }

        /**
     * Validate credit card form fields
     *
     * @access      public
     * @return      void
     */
    public function validate_fields() {

        $form_fields = array(
            'card-number' => array(
                'name'       => __( 'Credit Card Number', 'stripe-for-woocommerce' ),
                'error_type' => isset( $_POST['store4wc-card-number'] ) ? $_POST['store4wc-card-number'] : null,
            ),
            'card-expiry' => array(
                'name'       => __( 'Credit Card Expiration', 'stripe-for-woocommerce' ),
                'error_type' => isset( $_POST['store4wc-card-expiry'] ) ? $_POST['store4wc-card-expiry'] : null,
            ),
            'card-cvc'    => array(
                'name'       => __( 'Credit Card CVC', 'stripe-for-woocommerce' ),
                'error_type' => isset( $_POST['store4wc-card-cvc'] ) ? $_POST['store4wc-card-cvc'] : null,
            ),
        );

        foreach ( $form_fields as $form_field ) {

            if ( ! empty( $form_field['error_type'] ) ) {
                wc_add_notice( $this->get_form_error_message( $form_field['name'], $form_field['error_type'] ), 'error' );
            }
        }
    }

        /**
     * Process the payment and return the result
     *
     * @access      public
     * @param       int $order_id
     * @return      array
     */
    public function process_payment( $order_id ) {

        if ( $this->send_to_stripe( $order_id ) ) {
            $this->order_complete();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $this->order )
            );

            return $result;
        } else {
            $this->payment_failed();
        }
    }

/**
     * Send form data to Stripe
     * Handles sending the charge to an existing customer, a new customer (that's logged in), or a guest
     *
     * @access      protected
     * @param       int $order_id
     * @return      bool
     */
    protected function send_to_stripe( $order_id ) {
        global $s4wc;

        // Get the order based on order_id
        $this->order = new WC_Order( $order_id );

        // Get the credit card details submitted by the form
        $this->form_data = $this->get_form_data();

        // If there are errors on the form, don't bother sending to Stripe.
        if ( $this->form_data['errors'] == 1 ) {
            return;
        }

        // Set up the charge for Stripe's servers
        try {

            // Allow for any type of charge to use the same try/catch config
            $this->charge_set_up();

            // Save data for the "Capture"
            update_post_meta( $this->order->id, '_s4wc_capture', strcmp( $this->settings['charge_type'], 'authorize' ) == 0 );

            // Save Stripe fee
            if ( isset( $this->charge->balance_transaction ) && isset( $this->charge->balance_transaction->fee ) ) {
                $stripe_fee = number_format( $this->charge->balance_transaction->fee / 100, 2, '.', '' );
                update_post_meta( $this->order->id, 'Stripe Fee', $stripe_fee );
            }

            return true;

        } catch ( Exception $e ) {

            // Stop page reload if we have errors to show
            unset( WC()->session->reload_checkout );

            $this->transaction_error_message = $s4wc->get_error_message( $e );

            wc_add_notice( __( 'Error:', 'stripe-for-woocommerce' ) . ' ' . $this->transaction_error_message, 'error' );

            return false;
        }
    }