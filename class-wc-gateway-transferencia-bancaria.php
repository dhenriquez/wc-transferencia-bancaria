<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name: Woocommerce Transferencia Bancaria Chile
 * Plugin URI: https://github.com/dhenriquez/wc-transferencia-bancaria
 * Description: Se muestra información bancaria con datos Chilenos para realizar transferencias.
 * Version: 1.0.0
 * Author: Daniel Henriquez
 * Author URI: https://www.dhenriquez.cl
 * WC requires at least: 3.4.0
 * WC tested up to: 5.1.0
 */

add_action( 'plugins_loaded', 'woocommerce_transferencia_bancaria_init' );
add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_transferencia_bancaria_gateway' );

function woocommerce_transferencia_bancaria_init() {

	/**
	 *
	 * @class       WC_Gateway_Transferencia_Bancaria
	 * @extends     WC_Payment_Gateway
	 * @version     1.0.0
	 */
	class WC_Gateway_Transferencia_Bancaria extends WC_Payment_Gateway {

		public function __construct() {

			$this->id                 = 'tc';
			$this->icon               = '';
			$this->has_fields         = false;
			$this->method_title       = __( 'Transferencia bancaria', 'woocommerce' );
			$this->method_description = __( 'Mostrar información para transferencia bancaria.', 'woocommerce' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			$this->account_details = get_option(
				'woocommerce_tc_accounts',
				array(
					array(
						'account_name' => $this->get_option( 'account_name' ),
						'account_number' => $this->get_option( 'account_number' ),
						'account_type' => $this->get_option( 'account_type' ),
						'bank_name' => $this->get_option( 'bank_name' ),
						'rut' => $this->get_option( 'rut' ),
						'email' => $this->get_option( 'email' ),
					),
				)
			);

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
			add_action( 'woocommerce_thankyou_tc', array( $this, 'thankyou_page' ) );

			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		public function init_form_fields() {

			$this->form_fields = array(
				'enabled'         => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable bank transfer', 'woocommerce' ),
					'default' => 'no',
				),
				'title'           => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Direct bank transfer', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions'    => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'account_details' => array(
					'type' => 'account_details',
				),
			);

		}

		public function generate_account_details_html() {

			ob_start();

			?>
			<tr valign="top">
				<th scope="row" class="titledesc"><?php esc_html_e( 'Account details:', 'woocommerce' ); ?></th>
				<td class="forminp" id="tc_accounts">
					<div class="wc_input_table_wrapper">
						<table class="widefat wc_input_table sortable" cellspacing="0">
							<thead>
								<tr>
									<th class="sort">&nbsp;</th>
									<th><?php esc_html_e( 'Account name', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Account number', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Bank name', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Account type', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'RUT', 'woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Email', 'woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody class="accounts">
								<?php
								$i = -1;
								if ( $this->account_details ) {
									foreach ( $this->account_details as $account ) {
										$i++;

										echo '<tr class="account">
											<td class="sort"></td>
											<td><input type="text" value="' . esc_attr( wp_unslash( $account['account_name'] ) ) . '" name="tc_account_name[' . esc_attr( $i ) . ']" /></td>
											<td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="tc_account_number[' . esc_attr( $i ) . ']" /></td>
											<td><input type="text" value="' . esc_attr( wp_unslash( $account['bank_name'] ) ) . '" name="tc_bank_name[' . esc_attr( $i ) . ']" /></td>
											<td><input type="text" value="' . esc_attr( $account['account_type'] ) . '" name="tc_account_type[' . esc_attr( $i ) . ']" /></td>
											<td><input type="text" value="' . esc_attr( $account['rut'] ) . '" name="tc_rut[' . esc_attr( $i ) . ']" /></td>
											<td><input type="text" value="' . esc_attr( $account['email'] ) . '" name="tc_email[' . esc_attr( $i ) . ']" /></td>
										</tr>';
									}
								}
								?>
							</tbody>
							<tfoot>
								<tr>
									<th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', 'woocommerce' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', 'woocommerce' ); ?></a></th>
								</tr>
							</tfoot>
						</table>
					</div>
					<script type="text/javascript">
						jQuery(function() {
							jQuery('#tc_accounts').on( 'click', 'a.add', function(){

								var size = jQuery('#tc_accounts').find('tbody .account').length;

								jQuery('<tr class="account">\
										<td class="sort"></td>\
										<td><input type="text" name="tc_account_name[' + size + ']" /></td>\
										<td><input type="text" name="tc_account_number[' + size + ']" /></td>\
										<td><input type="text" name="tc_bank_name[' + size + ']" /></td>\
										<td><input type="text" name="tc_account_type[' + size + ']" /></td>\
										<td><input type="text" name="tc_rut[' + size + ']" /></td>\
										<td><input type="text" name="tc_email[' + size + ']" /></td>\
									</tr>').appendTo('#tc_accounts table tbody');

								return false;
							});
						});
					</script>
				</td>
			</tr>
			<?php
			return ob_get_clean();

		}

		public function save_account_details() {

			$accounts = array();

			if ( isset( $_POST['tc_account_name'] ) && isset( $_POST['tc_account_number'] ) && isset( $_POST['tc_bank_name'] )
				&& isset( $_POST['tc_account_type'] ) && isset( $_POST['tc_rut'] ) && isset( $_POST['tc_email'] ) ) {

				$account_names		= wc_clean( wp_unslash( $_POST['tc_account_name'] ) );
				$account_numbers	= wc_clean( wp_unslash( $_POST['tc_account_number'] ) );
				$bank_names			= wc_clean( wp_unslash( $_POST['tc_bank_name'] ) );
				$account_type		= wc_clean( wp_unslash( $_POST['tc_account_type'] ) );
				$rut				= wc_clean( wp_unslash( $_POST['tc_rut'] ) );
				$email				= wc_clean( wp_unslash( $_POST['tc_email'] ) );

				foreach ( $account_names as $i => $name ) {
					if ( ! isset( $account_names[ $i ] ) ) {
						continue;
					}

					$accounts[] = array(
						'account_name'		=> $account_names[ $i ],
						'account_number'	=> $account_numbers[ $i ],
						'bank_name'			=> $bank_names[ $i ],
						'account_type'		=> $account_type[ $i ],
						'rut'				=> $rut[ $i ],
						'email'				=> $email[ $i ],
					);
				}
			}

			update_option( 'woocommerce_tc_accounts', $accounts );
		}

		public function thankyou_page( $order_id ) {

			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
			}
			$this->bank_details( $order_id );

		}

		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( ! $sent_to_admin && 'tc' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				if ( $this->instructions ) {
					echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
				}
				$this->bank_details( $order->get_id() );
			}

		}

		private function bank_details( $order_id = '' ) {

			if ( empty( $this->account_details ) ) {
				return;
			}

			$order = wc_get_order( $order_id );

			$tc_accounts = apply_filters( 'woocommerce_bacs_accounts', $this->account_details, $order_id );

			if ( ! empty( $tc_accounts ) ) {
				$account_html = '';
				$has_details  = false;

				foreach ( $tc_accounts as $tc_account ) {
					$tc_account = (object) $tc_account;

					if ( $tc_account->account_name ) {
						$account_html .= '<h3 class="wc-bacs-bank-details-account-name">' . wp_kses_post( wp_unslash( $tc_account->account_name ) ) . ':</h3>' . PHP_EOL;
					}

					$account_html .= '<ul class="wc-bacs-bank-details order_details bacs_details">' . PHP_EOL;

					$account_fields = apply_filters(
						'woocommerce_bacs_account_fields',
						array(
							'bank_name'      => array(
								'label' => __( 'Bank', 'woocommerce' ),
								'value' => $tc_account->bank_name,
							),
							'account_number' => array(
								'label' => __( 'Account number', 'woocommerce' ),
								'value' => $tc_account->account_number,
							),
							'account_type'      => array(
								'label' => __( 'Account type', 'woocommerce' ),
								'value' => $tc_account->account_type,
							),
							'rut'           => array(
								'label' => __( 'RUT', 'woocommerce' ),
								'value' => $tc_account->rut,
							),
							'email'            => array(
								'label' => __( 'Email', 'woocommerce' ),
								'value' => $tc_account->email,
							),
						),
						$order_id
					);

					foreach ( $account_fields as $field_key => $field ) {
						if ( ! empty( $field['value'] ) ) {
							$account_html .= '<li class="' . esc_attr( $field_key ) . '">' . wp_kses_post( $field['label'] ) . ': <strong>' . wp_kses_post( wptexturize( $field['value'] ) ) . '</strong></li>' . PHP_EOL;
							$has_details   = true;
						}
					}

					$account_html .= '</ul>';
				}

				if ( $has_details ) {
					echo '<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">' . esc_html__( 'Our bank details', 'woocommerce' ) . '</h2>' . wp_kses_post( PHP_EOL . $account_html ) . '</section>';
				}
			}

		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order->get_total() > 0 ) {
				// Mark as on-hold (we're awaiting the payment).
				$order->update_status( apply_filters( 'woocommerce_bacs_process_payment_order_status', 'on-hold', $order ), __( 'Esperando Transferencia Bancaria', 'woocommerce' ) );
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thankyou redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}

	}

	function woocommerce_add_transferencia_bancaria_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Transferencia_Bancaria'; 
		return $methods;
	}

}