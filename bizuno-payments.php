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

// Library files for plugin operations
require_once ( dirname(__FILE__) . '/lib/payfabric.php' );
require_once ( dirname(__FILE__) . '/lib/purchase_order.php' );

class bizuno_payments
{
    public function __construct()
    {
        if ( is_plugin_active ( 'woocommerce/woocommerce.php' ) ) {
            add_action ( 'plugins_loaded',                                    [ $this, 'bizuno_payments_init' ], 11 ); // Priority 11 = after WooCommerce
            add_action ( 'woocommerce_checkout_process',                      [ $this, 'bizuno_payfabric_payment' ] );
            add_action ( 'woocommerce_checkout_update_order_meta',            [ $this, 'bizuno_payfabric_update_order_meta' ] );
            add_action ( 'woocommerce_admin_order_data_after_billing_address',[ $this, 'bizuno_payfabric_order_meta' ], 10, 1 );
            add_action ( 'woocommerce_checkout_order_processed',              [ $this, 'payfabric_fetch_order_id_before_payment' ], 10, 3);
            // WordPress Filters
            add_filter ( 'woocommerce_payment_gateways',                      [ $this, 'add_payment_gateways' ] );
            add_filter ( 'woocommerce_available_payment_gateways',            [ $this, 'disable_purchaseorder' ], 99, 1);
            add_filter ( 'plugin_action_links_payfabric',                     [ $this, 'payfabric_gateway_action_links' ]);
        }
    }

    public function payfabric_gateway_action_links($links)
    {
        $plugin_links = [ '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payfabric') . '">' . __('Settings PayFabric (new)', 'bizuno-payments') . '</a>' ];
        return array_merge($plugin_links, $links);
    }
    
    // 2023-10-25 - Added by PhreeSoft to fetch the order ID to pass to PayFabric.
    // This prevents a bug that causes a critical error on the site after payment has been processed as null is passed for the order ID
    // to payfabric causing the callback to report null as the order ID to look up to complete the order.
    function payfabric_fetch_order_id_before_payment( $order_id, $posted_data, $order ) {
        $GLOBALS['payfabric_pending_order_id'] = $order_id;
    }

    public function bizuno_payments_init()
    {
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
            add_action( 'admin_notices', function() { echo '<div class="notice notice-error"><p>Bizuno Payments requires WooCommerce to be active.</p></div>'; } );
            return;
        }
        bizuno_payfabric_gateway_class();
        bizuno_payment_purchase_order_class();
    }

    public function add_payment_gateways( $gateways )
    {
        $gateways[] = 'WC_Gateway_PayFabric';
        $gateways[] = 'WC_Gateway_PurchaseOrder';
        return $gateways;
    }
    
    public function bizuno_payfabric_payment()
    {
        if($_POST['payment_method'] != 'payfabric') { return; }
        if( !isset($_POST['mobile']) || empty($_POST['mobile']) )           { wc_add_notice( __( 'Please add your mobile number', 'bizuno-payments' ), 'error' ); }
        if( !isset($_POST['transaction']) || empty($_POST['transaction']) ) { wc_add_notice( __( 'Please add your transaction ID', 'bizuno-payments' ), 'error' ); }
    }

    public function bizuno_payfabric_update_order_meta( $order_id ) // Update the order meta with field value
    { 
        if($_POST['payment_method'] != 'payfabric') { return; }
        update_post_meta( $order_id, 'mobile', $_POST['mobile'] );
        update_post_meta( $order_id, 'transaction', $_POST['transaction'] );
    }

    public function bizuno_payfabric_order_meta($order) // Display field value on the order edit page
    {
        $method = get_post_meta( $order->id, '_payment_method', true );
        if($method != 'payfabric') { return; }
        $mobile = get_post_meta( $order->id, 'mobile', true );
        $transaction = get_post_meta( $order->id, 'transaction', true );
        echo '<p><strong>'. esc_html ( __( 'Mobile Number', 'bizuno-payments' ) ) . ':</strong> ' . esc_html ( $mobile ) . '</p>';
        echo '<p><strong>'.esc_html ( __( 'Transaction ID', 'bizuno-payments' ) ) . ':</strong> ' . esc_html ($transaction ) . '</p>';
    }
    
    public function disable_purchaseorder( $available_gateways ) { // Disable PO Method if the user is not logged in or doesn't have a contact ID link to Bizuno
        $disable = false;
        $user = wp_get_current_user(); // Check to see if user has permission to use this method
        if (empty($user)) { $disable = true; } // not logged in, we're done
        else {
            $cID = (int)get_user_meta( $user->ID, 'bizuno_payment_allow_po', true); // bizuno_wallet_id
            if (empty($cID)) { $disable = true; } // not linked to Bizuno contact, we're done
        }
        if ( $disable ) { unset($available_gateways['purchaseorder']); }
        return $available_gateways;
    }
}
new bizuno_payments();
