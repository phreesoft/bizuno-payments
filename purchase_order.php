<?php
/**
 * Bizuno Payments Plugin - Purchase Order Gateway
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
 * @filesource /lib/purchase_order.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function bizuno_payment_purchase_order_class() {
    class WC_Gateway_PurchaseOrder extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id                 = 'purchorder';
            //$this->icon             = apply_filters( 'bizuno_api_purchorder_icon', '' );
            $this->has_fields         = false;
            $this->method_title       = _x( 'Purchase Order payments', 'Purchase Order payment method', 'bizuno-payments' );
            $this->method_description = __( 'Accept payment via business Purchase Order. This offline gateway can also be useful to test purchases.', 'bizuno-payments' );
            $this->init_form_fields();
            $this->init_settings();
            $this->title              = $this->get_option( 'title' );
            $this->description        = $this->get_option( 'description' );
            $this->instructions       = $this->get_option( 'instructions' );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options'] );
            add_action( 'woocommerce_thankyou_purchorder',                          [$this, 'thankyou_page'] );
            add_action( 'woocommerce_email_before_order_table',                     [$this, 'email_instructions'], 10, 3 );
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled'     => ['title'=>__( 'Enable/Disable', 'bizuno-payments' ),'type'=>'checkbox','default'=>'no',
                    'label'      => __( 'Enable PO Checkout', 'bizuno-payments' )],
                'title'       => ['title'=>__( 'Title', 'bizuno-payments' ),         'type'=>'text',    'desc_tip'=>true,
                    'description'=> __( 'This controls the title which the user sees during checkout.', 'bizuno-payments' ),
                    'default'    => _x( 'Purchase Order', 'Purchase Order payment method', 'bizuno-payments' )],
                'description' => ['title'=>__( 'Description', 'bizuno-payments' ),   'type'=>'textarea','desc_tip'=>true,
                    'description'=> __( 'Payment method description that the customer will see on your checkout.', 'bizuno-payments' ),
                    'default'    => __( 'You will receive an invoice with tracking once your order ships.', 'bizuno-payments' )],
                'instructions'=> ['title'=>__( 'Instructions', 'bizuno-payments' ),  'type'=>'textarea','desc_tip'=>true, 'default'=>'',
                    'description'=> __( 'Instructions that will be added to the thank you page and emails.', 'bizuno-payments' )]];
        }

        public function thankyou_page()
        {
            if ( $this->instructions ) { echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) ); }
        }

        public function email_instructions( $order, $sent_to_admin ) // removed last param: , $plain_text = false
        {
            if ( $this->instructions && ! $sent_to_admin && 'purchorder' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
                echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
            }
        }

        public function process_payment( $order_id )
        {
            $order = wc_get_order( $order_id );
            if ( $order->get_total() > 0 ) { // Mark as on-hold (we're awaiting the purchorder).
                $order->update_status( apply_filters( 'bizuno_api_purchorder_process_payment_order_status', 'on-hold', $order ), _x( 'Awaiting check payment', 'Check payment method', 'bizuno-payments' ) );
            } else { $order->payment_complete(); }
            WC()->cart->empty_cart();
            return ['result'=>'success', 'redirect'=>$this->get_return_url( $order )];
        }
    }
}

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

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
