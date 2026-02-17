<?php
/**
 * Plugin Name:       Bizuno Payments
 * Plugin URI:        https://github.com/PhreeSoft/bizuno-payments
 * Description:       Payment gateways for PayFabric and Purchase Orders (WAC)
 * Version:           1.0.0
 * Author:            PhreeSoft
 * Author URI:        https://phreesoft.com
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain:       bizuno-payments
 * Domain Path:       /locale
 * WC requires at least: 8.0
 * WC tested up to:   9.7
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once ( dirname(__FILE__) . '/payfabric/payfabric.php' );
require_once ( dirname(__FILE__) . '/purchase_order.php' );

class bizuno_payments
{
    public function __construct()
    {
        // WordPress Actions
        add_action ( 'plugins_loaded',                                    [ $this, 'bizuno_payments_plugins_loaded' ] );
        add_action ( 'woocommerce_checkout_process',                      [ $this, 'bizuno_payments_payment' ] );
        add_action ( 'woocommerce_checkout_update_order_meta',            [ $this, 'bizuno_payments_update_order_meta' ] );
        add_action ( 'woocommerce_admin_order_data_after_billing_address',[ $this, 'bizuno_payments_order_meta' ], 10, 1 );
        // WordPress Filters
        add_filter ( 'woocommerce_payment_gateways',                      [ $this, 'bizuno_payments_add_to_gateways' ] );
        add_filter ( 'woocommerce_available_payment_gateways',            [ $this, 'bizuno_api_disable_purchorder' ], 99, 1);
    }

    public function bizuno_payments_plugins_loaded()
    {
        if ( ! is_plugin_active ( 'woocommerce/woocommerce.php' ) ) { return; }
        // Load Woocommerce plugins only if WooCommerce is installed and active
//        bizuno_payfabric_gateway_class();
//        bizuno_payment_purchase_order_class();
//        WC()->frontend_includes();
//        if ( class_exists ( 'WC_Payment_Gateway' ) ) { // get instance of WooCommerce for Payfabric
//            require ( plugin_dir_path ( __FILE__ ) . 'payfabric/classes/class-payfabric-gateway-woocommerce.php' );
//            Payfabric_Gateway_Woocommerce::get_instance();
//        }
    }

    public function bizuno_payments_add_to_gateways( $gateways )
    {
        $gateways[] = 'WC_Gateway_PayFabric';
        $gateways[] = 'WC_Gateway_PurchOrder';
        return $gateways;
    }
    
    public function bizuno_payments_payment()
    {
        if($_POST['payment_method'] != 'custom') { return; }
        if( !isset($_POST['mobile']) || empty($_POST['mobile']) )           { wc_add_notice( __( 'Please add your mobile number', 'bizuno-payments' ), 'error' ); }
        if( !isset($_POST['transaction']) || empty($_POST['transaction']) ) { wc_add_notice( __( 'Please add your transaction ID', 'bizuno-payments' ), 'error' ); }
    }

    public function bizuno_payments_update_order_meta( $order_id ) // Update the order meta with field value
    { 
        if($_POST['payment_method'] != 'custom') { return; }
        update_post_meta( $order_id, 'mobile', $_POST['mobile'] );
        update_post_meta( $order_id, 'transaction', $_POST['transaction'] );
    }

    public function bizuno_payments_order_meta($order) // Display field value on the order edit page
    {
        $method = get_post_meta( $order->id, '_payment_method', true );
        if($method != 'custom') { return; }
        $mobile = get_post_meta( $order->id, 'mobile', true );
        $transaction = get_post_meta( $order->id, 'transaction', true );
        echo '<p><strong>'. esc_html ( __( 'Mobile Number', 'bizuno-payments' ) ) . ':</strong> ' . esc_html ( $mobile ) . '</p>';
        echo '<p><strong>'.esc_html ( __( 'Transaction ID', 'bizuno-payments' ) ) . ':</strong> ' . esc_html ($transaction ) . '</p>';
    }
    
    public function bizuno_api_disable_purchorder( $available_gateways ) { // Disable PO Method if the user is not logged in or doesn't have a contact ID link to Bizuno
        $disable = false;
        $user = wp_get_current_user(); // Check to see if user has permission to use this method
        if (empty($user)) { $disable = true; } // not logged in, we're done
        else {
            $cID = (int)get_user_meta( $user->ID, 'bizuno_payment_allow_po', true); // bizuno_wallet_id
            if (empty($cID)) { $disable = true; } // not linked to Bizuno contact, we're done
        }
        if ( $disable ) { unset($available_gateways['purchorder']); }
        return $available_gateways;
    }
}
new bizuno_payments();
