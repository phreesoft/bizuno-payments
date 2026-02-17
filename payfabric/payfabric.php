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

function bizuno_payfabric_gateway_class()
{
    class WC_Gateway_PayFabric extends WC_Payment_Gateway
    {
        public $domain = 'bizuno-payfabric';

        public function __construct() {
            $this->id                 = 'custom';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Custom', 'bizuno-payfabric' );
            $this->method_description = __( 'Allows payments with custom gateway.', 'bizuno-payfabric' );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'completed' );
            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
            add_action( 'woocommerce_thankyou_' . $this->id,                        [ $this, 'thankyou_page' ] );
            add_action( 'woocommerce_email_before_order_table',                     [ $this, 'email_instructions' ], 10, 3 ); // Customer Emails
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled'      => [ 'title' => __( 'Enable/Disable', 'bizuno-payfabric' ), 'type' => 'checkbox', 'default' => 'yes',
                    'label'       => __( 'Enable Custom Payment', 'bizuno-payfabric' )],
                'title'        => [ 'title' => __( 'Title', 'bizuno-payfabric' ), 'type' => 'text', 'default' => __( 'Custom Payment', 'bizuno-payfabric' ), 'desc_tip' => true,
                    'description' => __( 'This controls the title which the user sees during checkout.', 'bizuno-payfabric' )],
                'order_status' => [ 'title' => __( 'Order Status', 'bizuno-payfabric' ), 'type' => 'select', 'default' => 'wc-completed', 'class' => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', 'bizuno-payfabric' ), 'desc_tip' => true, 'options' => wc_get_order_statuses() ],
                'description'  => [ 'title' => __( 'Description', 'bizuno-payfabric' ), 'type' => 'textarea', 'default' => __('Payment Information', 'bizuno-payfabric'),
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'bizuno-payfabric' ), 'desc_tip' => true ],
                'instructions' => [ 'title' => __( 'Instructions', 'bizuno-payfabric' ), 'type' => 'textarea', 'default' => '',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'bizuno-payfabric' ), 'desc_tip' => true ] ];
        }

        public function thankyou_page()
        {
            if ( $this->instructions ) { echo wp_kses_post ( wpautop( wptexturize( $this->instructions ) ) ); }
        }

        public function email_instructions( $order, $sent_to_admin )// removed last param: , $plain_text = false
        {
            if ( $this->instructions && ! $sent_to_admin && 'custom' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wp_kses_post ( wpautop ( wptexturize( $this->instructions ) ) ) . PHP_EOL;
            }
        }

        public function payment_fields(){
            if ( $description = $this->get_description() ) { echo wp_kses_post ( wpautop ( wptexturize( $description ) ) );  }
?>
<div id="custom_input">
    <p class="form-row form-row-wide">
        <label for="mobile" class=""><?php esc_html_e('Mobile Number', 'bizuno-payfabric'); ?></label>
        <input type="text" class="" name="mobile" id="mobile" placeholder="" value="">
    </p>
    <p class="form-row form-row-wide">
        <label for="transaction" class=""><?php esc_html_e('Transaction ID', 'bizuno-payfabric'); ?></label>
        <input type="text" class="" name="transaction" id="transaction" placeholder="" value="">
    </p>
</div>
<?php
        }

        public function process_payment( $order_id ) {
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
        }
    }
}
