<?php
if ( ! class_exists( 'Ced_Pricing' ) ) {

	class Ced_Pricing {

		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'wp_ajax_ced_woo_pricing_plan_selection', array( $this, 'ced_woo_pricing_plan_selection' ) );
			add_action( 'wp_ajax_ced_woo_pricing_plan_cancellation', array( $this, 'ced_woo_pricing_plan_cancellation' ) );
			add_action( 'admin_init', array( $this, 'ced_save_subscription_details' ) );
		}

		public function enqueue_styles() {
			wp_enqueue_style( 'ced_pricing_plan', plugin_dir_url( __FILE__ ) . 'css/pricing-plan.css', array(), '1.0.0', 'all' );
		}

		public function enqueue_scripts() {
			wp_enqueue_script( 'ced_pricing_plan', plugin_dir_url( __FILE__ ) . '/js/ced_pricing_plan.js', array( 'jquery' ), '1.0.0', false );
			$ajax_nonce     = wp_create_nonce( 'ced-pricing-ajax-seurity-string' );
			$localize_array = array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'ajax_nonce' => $ajax_nonce,
			);
			wp_localize_script( 'ced_pricing_plan', 'ced_pricing_admin_obj', $localize_array );
		}

		public function ced_woo_pricing_plan_selection() {
			$check_ajax = check_ajax_referer( 'ced-pricing-ajax-seurity-string', 'ajax_nonce' );
			if ( $check_ajax ) {
				$params                  = array();
				$pricing_subscribed_data = get_option( 'ced_unified_contract_details', array() );

				if ( ! empty( $pricing_subscribed_data['amazon']['plan_status'] ) ) {
					$pricing_status = $pricing_subscribed_data['amazon']['plan_status'];
				} else {
					$pricing_status = '';
				}

				$params['plan_type'] = ! empty( $_POST['plan_type'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_type'] ) ) : '';
				$params['plan_cost'] = ! empty( $_POST['plan_cost'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_cost'] ) ) : '';

				if ( 'canceled' !== $pricing_status ) {
					$params['contract_id'] = ! empty( $_POST['contract_id'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_id'] ) ) : '';
				}

				$params['plan_period']  = ! empty( $_POST['plan_period'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_period'] ) ) : '';
				$params['channel']      = 'amazon';
				$params['redirect_url'] = home_url();

				$url = 'https://api.cedcommerce.com/woobilling/live/ced-process-payment.php?' . http_build_query( $params );

				$response = wp_remote_get( $url, array(
					'timeout'   => 20,
					'sslverify' => false,
				) );

				if ( is_wp_error( $response ) ) {
					$output = array(
						'status'  => false,
						'message' => $response->get_error_message(),
					);
				} else {
					$output = json_decode( wp_remote_retrieve_body( $response ), true );
				}

				echo wp_json_encode( $output );
				wp_die();
			}
		}


		public function ced_save_subscription_details() {
			// Home.php
			if ( ! empty( $_GET['success'] ) && 'yes' == $_GET['success'] && ! empty( $_GET['contract_id'] ) && isset( $_GET['page'] ) && 'sales_channel' == $_GET['page'] ) {
				$data              = get_option( 'ced_unified_contract_details', array() );
				$contract_id       = isset( $_GET['contract_id'] ) ? sanitize_text_field( wp_unslash( $_GET['contract_id'] ) ) : '';
				$plan_name         = isset( $_GET['plan_name'] ) ? sanitize_text_field( wp_unslash( $_GET['plan_name'] ) ) : '';
				$billing_period    = isset( $_GET['billing_period'] ) ? sanitize_text_field( wp_unslash( $_GET['billing_period'] ) ) : '';
				$plan_status       = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
				$next_payment_date = isset( $_GET['next_payment_date'] ) ? sanitize_text_field( wp_unslash( $_GET['next_payment_date'] ) ) : '';
				$end_date          = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
				$price             = isset( $_GET['plan_cost'] ) ? sanitize_text_field( wp_unslash( $_GET['plan_cost'] ) ) : '';

				$data['amazon'] = array(
					'contract_id'       => $contract_id,
					'plan_name'         => $plan_name,
					'billing_period'    => $billing_period,
					'plan_status'       => $plan_status,
					'next_payment_date' => $next_payment_date,
					'end_date'          => $end_date,
					'price'             => $price,
				);
				update_option( 'ced_unified_pricing_selected', 'true' );
				update_option( 'ced_unified_contract_details', $data );

				// newly added code
				$temp_data = array( 'plan_status' => 'active', 'end_date' => '' );
				set_transient( 'ced_unified_plan_details', $temp_data, 3600 );

			}
			
		}


		public function ced_woo_pricing_plan_cancellation() {
			$check_ajax = check_ajax_referer( 'ced-pricing-ajax-seurity-string', 'ajax_nonce' );
			if ( $check_ajax ) {

				$params                 = array();
				$params['contract_id']  = ! empty( $_POST['contract_id'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_id'] ) ) : '';
				$params['channel']      = 'amazon';
				$params['is_cancel']    = 'yes';
				$params['redirect_url'] = home_url();

				$url = 'https://api.cedcommerce.com/woobilling/live/ced-process-payment.php?' . http_build_query( $params );

				$response = wp_remote_get( $url, array(
					'timeout'   => 20,
					'sslverify' => false,
				) );

				if ( is_wp_error( $response ) ) {
					echo wp_json_encode( array(
						'status'  => false,
						'message' => $response->get_error_message(),
					) );
					wp_die();
				}

				$response_data = json_decode( wp_remote_retrieve_body( $response ), true );

				$data = get_option( 'ced_unified_contract_details', array() );

				if ( ! empty( $response_data ) && isset( $response_data['status'] ) && '200' == $response_data['status'] ) {

					$data['amazon']['plan_status']       = 'canceled';
					$data['amazon']['end_date']          = $response_data['data']['end_date'];
					$data['amazon']['next_payment_date'] = '';

					update_option( 'ced_unified_contract_details', $data );

					$transient_data = array(
						'plan_status' => 'canceled',
						'end_date'    => '',
					);
					set_transient( 'ced_unified_plan_details', $transient_data, 3600 );
				}

				echo wp_json_encode( $response_data );
				wp_die();
			}
		}

	}
}
