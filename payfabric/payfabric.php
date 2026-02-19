<?php
/**
 * Bizuno Payments Plugin - Payfabric Gateway
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    1.x Last Update: 2026-02-17
 * @filesource /payfabric/payfabric.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

//require_once dirname(__FILE__) . '/class-payfabric-gateway.php'; // This is rolled into this class
require_once dirname(__FILE__) . '/class-payfabric-gateway-request.php';

define('BIZUNO_PAYMENTS_PAYFABRIC_NAME',    'Bizuno-PayFabric-Gateway');
define('BIZUNO_PAYMENTS_PAYFABRIC_VERSION', '3.0.0');

/*Define live and test gateway host */
define('LIVEGATEWAY', 'https://www.payfabric.com');
define('TESTGATEWAY', 'https://sandbox.payfabric.com');

/*
* Define log dir, severity level of logging mode and whether enable on-screen debug ouput.
* PLEASE DO NOT USE "DEBUG" LOGGING MODE IN PRODUCTION
*/
define('PayFabric_LOG_SEVERITY', 'INFO');
define('PayFabric_LOG_DIR',      dirname(__FILE__) . '/logs');
define('PayFabric_DEBUG',        false);

function bizuno_payfabric_gateway_class()
{
    class WC_Gateway_PayFabric extends WC_Payment_Gateway
    {
        public  $domain = 'bizuno-payfabric';
        private $show_log_field   = '0'; // Define the control parameter value to determine whether the LOG functionality show or not
        private $show_auth_fields = '1'; // Define the control parameter value to determine whether the AUTH functionality show or not
        private $integration_show = '1'; // Define whether integration mode should be shown or not, 1 means to show, 0 means not
        
        public  $testmode;
        public  $merchant_id;
        public  $password;
        public  $success_status;
        public  $payment_action;
        public  $payment_modes;

        public function __construct()
        {
            $this->id                 = 'payfabric';
            $this->icon               = plugins_url( 'assets/images/logo.png', __FILE__ );
            $this->method_title       = __( 'PayFabric', 'bizuno-payfabric' );
            $this->method_description = __( 'Allows credit card and e-check payments with the payfabric gateway.', 'bizuno-payments' );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables
            $this->title          = $this->get_option('title');
            $this->description    = $this->get_option('description');
            $this->testmode       = 'yes' === $this->get_option('testmode', 'no');
            $this->merchant_id    = defined('PF_OAUTH2_ID') && !empty(PF_OAUTH2_ID) ? PF_OAUTH2_ID : $this->get_option('merchant_id');
            $this->password       = defined('PF_OAUTH2_PW') && !empty(PF_OAUTH2_PW) ? PF_OAUTH2_PW : $this->get_option('password');
            $this->success_status = $this->get_option('success_status');
            $this->payment_action = $this->get_option('payment_action');
            $this->payment_modes  = $this->get_option('payment_modes');
            
            $this->supports = array( 'blocks', 'refunds' ); // 'subscriptions', // If you support it.
            
            // For Blocks: Register the gateway.
            if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_blocks_payment_method' ) );
            }

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
            add_action( 'woocommerce_thankyou_' . $this->id,                        [ $this, 'thankyou_page' ] );
            add_action( 'woocommerce_email_before_order_table',                     [ $this, 'email_instructions' ], 10, 3 ); // Customer Emails
            add_action( 'woocommerce_receipt_payfabric',                            [ $this, 'receipt_page' ] ); // Generate button or iframe ready to pay on receipt page
            add_action( 'wp',                                                       [ $this, 'payfabric_response_handler' ] ); // Payment response handler get
            add_action( 'wp_ajax_get_session',                                      [ $this, 'get_session' ] ); // Ajax request
            add_action( 'wp_ajax_nopriv_get_session',                               [ $this, 'get_session' ] ); // Ajax request
            add_action( 'woocommerce_my_account_my_orders_actions',                 [ $this, 'my_orders_actions' ] ); // My account actions
            add_action( 'woocommerce_admin_order_data_after_shipping_address',      [ $this, 'show_evo_transaction_id']); // Customize admin order detail page to show transaction ID
            add_action( 'woocommerce_api_payfabric',                                [ $this, 'handle_call_back' ] ); // Payment response handler if a post request
            add_action( 'woocommerce_order_action_payfabric_capture_charge',        [ $this, 'maybe_capture_charge' ] ); // Capture when the Capture Online is submitted
            add_action( 'woocommerce_order_action_payfabric_void_charge',           [ $this, 'maybe_void_charge' ] ); // Void when the Void Online is submitted
            // Filters
            add_filter( 'woocommerce_get_return_url',                               [ $this, 'process_payment_return_url' ], 10, 2 );
//          add_filter( 'woocommerce_payment_gateways',                             [ $this, 'add_new_gateway' ] ); // Handled in Bizuno Payments main class
            add_filter( 'woocommerce_order_actions',                                [ $this, 'add_void_charge_order_action' ] ); // add the VOID Online Order actions
            add_filter( 'woocommerce_order_actions',                                [ $this, 'add_capture_charge_order_action' ] ); // add the Capture Online Order actions
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'bizuno-api'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayFabric gateway', 'bizuno-api'),
                    'description' => __('Enable or disable the gateway.', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => 'no' ],
                'title' => [
                    'title' => __('Title', 'bizuno-api'),
                    'type' => 'text',
                    'description' => __('The title which the user sees during checkout.', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => __('PayFabric', 'bizuno-api') ],
                'description' => [
                    'title' => __('Description', 'bizuno-api'),
                    'type' => 'textarea',
                    'description' => __('The description which the user sees during checkout.', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => __("Pay via PayFabric", 'bizuno-api') ],
                'testmode' => [
                    'title' => __('PayFabric test mode', 'bizuno-api'),
                    'type' => 'checkbox',
                    'label' => __('Enable test mode', 'bizuno-api'),
                    'description' => __('Enable or disable the test mode for the gateway to test the payment method.', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => 'yes' ],
                'advanced' => [
                    'title' => __('Advanced options', 'bizuno-api'),
                    'type' => 'title',
                    'description' => '' ],
                'api_merchant' => [
                    'title' => __('Merchant data', 'bizuno-api'),
                    'type' => 'title',
                    'description' => __('In this section You can set up your merchant data for PayFabric system.', 'bizuno-api') ],
                'merchant_id' => [
                    'title' => __('Device ID', 'bizuno-api'),
                    'type' => 'text',
                    'description' => __('Device ID from PayFabric', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => '' ],
                'password' => [
                    'title' => __('Password', 'bizuno-api'),
                    'type' => 'password',
                    'description' => __('Device password from PayFabric', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => '' ],
                'success_status' => [ //choose the default paid order status
                    'title' => __('Success status', 'bizuno-api'),
                    'type' => 'select',
                    'description' => __('Status of order after successful payment.', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => 0,
                    'options' => [
                        __('Processing', 'bizuno-api'),
                        __('Completed', 'bizuno-api') ] ] ];
            if ($this->integration_show) {
                $this->form_fields['payment_modes'] = [
                    'title' => __('Payment mode', 'bizuno-api'),
                    'type' => 'select',
                    'description' => sprintf('Payment Mode controls the presentation of the Hosted Payment Page (HPP):<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<b>• Direct:</b> HPP shown directly on the checkout page, payment made when placing order. (A theme is required, see %sGuide%s).<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<b>• Iframe:</b> HPP is inside the shopping site page.<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;<b>• Redirect:</b> Shopping site redirects user to the HPP.', '<a href="https://github.com/PayFabric/WooCommerce-Plugin#readme" target="_blank">', '</a>' ),
                    'desc_tip' => false,
                    'default' => 2,
                    'options' => [ 2 => __('Direct', 'bizuno-api'), 0 => __('Iframe', 'bizuno-api'), 1 => __('Redirect', 'bizuno-api') ]];
            }
            if ($this->show_auth_fields) {
                $this->form_fields['payment_action'] = [
                    'title' => __('Payment action', 'bizuno-api'),
                    'type' => 'select',
                    'description' => __('Specify transaction type.', 'bizuno-api'),
                    'desc_tip' => true,
                    'default' => 0,
                    'options' => [ __('Purchase', 'bizuno-api'), __('Auth', 'bizuno-api') ] ];
            }
            if ($this->show_log_field) {
                $this->form_fields['log_mode'] = [
                    'title' => __('Logging', 'bizuno-api'),
                    'type' => 'checkbox',
                    'label' => __('Enable log debug', 'bizuno-api'),
                    'description' => __('Log payment events, such as gateway transaction callback, if enabled, log file will be found inside: wp-content/uploads/wc-logs', 'bizuno-api'),
                    'desc_tip' => false,
                    'default' => 'no' ];
            }
        }

        public function init_settings()
        {
            parent::init_settings();
            $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
        }

/*        public function logging($message) // Maybe used but ???
        {
            if ('yes' === $this->get_option('log_mode', 'no')) {
                $logger = new WC_Logger();
                $logger->add("$this->id", $message);
            }
        } */

        public function payment_fields()
        {
            try {
                $description = $this->get_description();
                if ($description) {
                    echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
                }
                if (2 == $this->payment_modes) {
                    $this->enqueue_styles();
                    $this->enqueue_js();
                    $payfabric_request = new PayFabric_Gateway_Request($this);
                    $payfabric_request->generate_payfabric_gateway_form(null, $this->testmode);
                }
            } catch (Exception $e) {
                wc_print_notice($e->getMessage(), 'error');
            }
        }

        private function enqueue_styles()
        {
            wp_enqueue_style(strtolower($this->plugin_name), plugin_dir_url(__FILE__) . 'assets/css/payfabric-gateway-woocommerce.css', array(), $this->version, 'all');
        }

        private function enqueue_js()
        {
            wp_enqueue_script(strtolower($this->plugin_name), plugin_dir_url(__FILE__) . 'assets/js/payfabric-gateway-woocommerce.js', ['jquery'], $this->version, true);
        }

        public function admin_options()
        {
?>
<h3><?php echo wp_kses_post ( $this->method_title ); ?></h3>
<?php echo (!empty($this->method_description)) ? wp_kses_post ( wpautop($this->method_description) ) : ''; ?>
<table class="form-table"><?php $this->generate_settings_html(); ?></table>
<?php
        }

        public function process_admin_options()
        {
            try {
                $post_data = $this->get_post_data();
//                $merchant_id = $this->get_field_key('merchant_id');
//                $merchant_password = $this->get_field_key('password');
//                $testmode = $this->get_field_key('testmode');
//                $payment_action = $this->get_field_key('payment_action');
                $merchant_id       = defined('PF_OAUTH2_ID') && !empty(PF_OAUTH2_ID) ? PF_OAUTH2_ID : (isset($post_data[$merchant_id]) ? $post_data[$merchant_id] : null);
                $merchant_password = defined('PF_OAUTH2_PW') && !empty(PF_OAUTH2_PW) ? PF_OAUTH2_PW : (isset($post_data[$merchant_password]) ? $post_data[$merchant_password] : null);
                $testmode = isset($post_data[$testmode]) ? $post_data[$testmode] : null;
                $payment_action = isset($post_data[$payment_action]) ? $post_data[$payment_action] : null;
                if (empty($merchant_id) || empty($merchant_password)) {
                    WC_Admin_Settings::add_error(__('Device ID or Password cannot be blank', 'bizuno-api'));
                } else {
                    $payfabric_request = new PayFabric_Gateway_Request($this);
                    $payfabric_request->do_check_gateway($testmode, $merchant_id, $merchant_password, $payment_action);
                    parent::process_admin_options();
                }
            } catch (Exception $e) {
                WC_Admin_Settings::add_error($e->getMessage());
            }
        }

        public function thankyou_page()
        {
//            if ( $this->instructions ) { echo wp_kses_post ( wpautop( wptexturize( $this->instructions ) ) ); }
        }

        public function email_instructions( $order, $sent_to_admin )// removed last param: , $plain_text = false
        {
//            if ( $this->instructions && ! $sent_to_admin && 'custom' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
//                echo wp_kses_post ( wpautop ( wptexturize( $this->instructions ) ) ) . PHP_EOL;
//            }
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            //If direct payment mode then do update process
            if (2 == $this->payment_modes) {
                $payfabric_request = new PayFabric_Gateway_Request($this);
                $payfabric_request->do_update_process($this->testmode, $order);
                return [ 'result' => 'success', 'redirect' => $this->get_return_url($order), 'key' => $order->get_order_key() ];
            } else {
                return [ 'result' => 'success', 'redirect' => $order->get_checkout_payment_url(true) ];
            }
        }

/*      public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
            // Set order status
            $order->update_status( $status, __( 'Checkout with custom payment. ', 'bizuno-payfabric' ) );
            // or call the Payment complete
            // $order->payment_complete();
            // Reduce stock levels
            $order->reduce_order_stock();
            // Remove cart
            WC()->cart->empty_cart();
            // Return thankyou redirect
            return ['result'=>'success', 'redirect'=>$this->get_return_url( $order )];
        } */

        public function process_payment_return_url( $url, $order )
        {
            // Example: Add a custom parameter
//            $url = add_query_arg( 'utm_source', 'payment_success', $url );

            // Or redirect to a custom thank-you page
            // $url = home_url( '/custom-thank-you/?order=' . $order->get_id() );

            return $url;
        }

        public function receipt_page($order_id)
        {
            try {
                $this->enqueue_styles();

                $order = wc_get_order($order_id);
                $payfabric_request = new PayFabric_Gateway_Request($this);

                $payfabric_request->generate_payfabric_gateway_form($order, $this->testmode);
            } catch (Exception $e) {
                wc_print_notice($e->getMessage(), 'error');
            }
        }

        //http://localhost/wordpress/index.php/checkout/order-received/721/?wcapi=payfabric&order_id=721&TrxKey=22062301958907&key=wc_order_jopIHjPEamN1y
        public function payfabric_response_handler()
        {
            try {
                if (isset($_GET['wcapi']) && isset($_GET['TrxKey']) && empty($_GET['wc-ajax'])) {
                    $merchantTxId = $_GET['TrxKey'];
                    $payfabric_request = new PayFabric_Gateway_Request($this);
                    $payfabric_request->generate_check_request_form($merchantTxId, $this->testmode);
                }
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        public function get_session()
        {
            echo wp_kses_post ( wp_send_json_success(
                array(
                    'token' => WC()->session->get('transaction_token')
                )
            ) );
            wp_die();
        }

        public function my_orders_actions($actions)
        {
            if (2 == $this->payment_modes) {
                unset($actions['pay']);
            }
            return $actions;
        }

        public function show_evo_transaction_id($order)
        {
            if($order->get_payment_method() == 'payfabric') {
                $transaction_id = get_post_meta($order->get_id(), '_transaction_id', true);
                if (!empty($transaction_id)) {
                    echo wp_kses_post ( '<h3>' . $this->method_title . ' ID </h3>' );
                    echo wp_kses_post ( "<p>$transaction_id</p>" );
                }
            }
        }

        //the method to response to the Gateway post callback when the user complete the payment
        public function handle_call_back()
        {
            try {
                $raw_post = file_get_contents('php://input');
                $parts = wp_parse_url($raw_post);
                parse_str($parts['path'], $query);
                $this->logging('Gateway post callback: ' . json_encode($query));
                if (isset($query['TrxKey'])) {
                    $merchantTxId = $query['TrxKey'];
                } else {
                    return __('Bad identifier.', 'bizuno-api');
                }

                $payfabric_request = new PayFabric_Gateway_Request($this);
                $payfabric_request->generate_check_request_form($merchantTxId, $this->testmode);
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        public function capture_payment($order_id)
        {
            $order = wc_get_order($order_id);
            if ($order->get_payment_method() == 'payfabric') {
                $merchantTxId = get_post_meta($order->get_id(), '_transaction_id', true);
                $old_wc = version_compare(WC_VERSION, '3.0', '<');
                $payment_status = $old_wc ? get_post_meta($order_id, '_payment_status', true) : $order->get_meta('_payment_status', true);
                if ($merchantTxId && 'on-hold' == $payment_status) {
                    $payfabric_request = new PayFabric_Gateway_Request($this);
                    $amount = $order->get_total();
                    $payfabric_request->do_capture_process($this->testmode, $order, $merchantTxId, $amount);
                }
            }
        }

        public function maybe_capture_charge($order)
        {
            try {
                if (!is_object($order)) {
                    $order = wc_get_order($order);
                }

                $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
                $this->capture_payment($order_id);

                return true;
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            try {
                $order = wc_get_order($order_id);
                if (!$order) {
                    return new WP_Error('invalid_order', 'Invalid Order ID');
                }
                $transaction_id = get_post_meta($order->get_id(), '_transaction_id', true);
                if (!$transaction_id) {
                    return new WP_Error('invalid_order', 'Invalid transaction ID');
                }
                $payfabric_request = new PayFabric_Gateway_Request($this);
                return $payfabric_request->do_refund_process($this->testmode, $transaction_id, $amount);
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        public function cancel_payment($order_id)
        {
            $order = wc_get_order($order_id);
            if ($order->get_payment_method() == 'payfabric') {
                $merchantTxId = get_post_meta($order->get_id(), '_transaction_id', true);
                $old_wc = version_compare(WC_VERSION, '3.0', '<');
                $payment_status = $old_wc ? get_post_meta($order_id, '_payment_status', true) : $order->get_meta('_payment_status', true);
                if ($merchantTxId && 'on-hold' == $payment_status) {
                    $payfabric_request = new PayFabric_Gateway_Request($this);
                    $payfabric_request->do_void_process($this->testmode, $order, $merchantTxId);
                }
            }
        }

        public function maybe_void_charge($order)
        {
            try {
                if (!is_object($order)) {
                    $order = wc_get_order($order);
                }

                $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
                $this->cancel_payment($order_id);

                return true;
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        public function add_void_charge_order_action($actions)
        {
            if (!isset($_REQUEST['post'])) {
                return $actions;
            }

            $order = wc_get_order($_REQUEST['post']);

            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $order_id = $old_wc ? $order->id : $order->get_id();
            $payment_method = $old_wc ? $order->payment_method : $order->get_payment_method();
            $payment_status = $old_wc ? get_post_meta($order_id, '_payment_status', true) : $order->get_meta('_payment_status', true);

            // exit if the order wasn't paid for with this gateway or the order has paid with Purchase action
            if ('payfabric' !== $payment_method || 'on-hold' !== $payment_status) {
                return $actions;
            }

            if (!is_array($actions)) {
                $actions = array();
            }

            $actions['payfabric_void_charge'] = esc_html__('VOID Online', 'bizuno-api' );

            return $actions;
        }

        public function add_capture_charge_order_action($actions)
        {
            if (!isset($_REQUEST['post'])) {
                return $actions;
            }

            $order = wc_get_order($_REQUEST['post']);

            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $order_id = $old_wc ? $order->id : $order->get_id();
            $payment_method = $old_wc ? $order->payment_method : $order->get_payment_method();
            $payment_status = $old_wc ? get_post_meta($order_id, '_payment_status', true) : $order->get_meta('_payment_status', true);

            // exit if the order wasn't paid for with this gateway or the order has paid with Purchase action
            if ('payfabric' !== strtolower($payment_method) || 'on-hold' !== $payment_status) {
                return $actions;
            }

            if (!is_array($actions)) {
                $actions = array();
            }

            $actions['payfabric_capture_charge'] = esc_html__('Capture Online', 'bizuno-api');

            return $actions;
        }

        /**
         * Blocks Checkout Support.
         */
        public function register_blocks_payment_method( $payment_method_registry ) {
            $payment_method_registry->register( new WC_PayFabric_Blocks_Payment_Method() );
        }
       
    } // End of class
} // end of function

// Block handler for Checkout Blocks.
if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    class WC_PayFabric_Blocks_Payment_Method extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
        public function initialize() {
            $this->name = 'payfabric';
        }

        public function is_active() {
            return true; // Or check settings.
        }

        public function get_payment_method_script_handles() {
            // Enqueue JS for blocks if needed.
            return array( 'payfabric-blocks' );
        }
    }
}
