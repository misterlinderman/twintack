<?php
if ( ! class_exists( 'Ced_Pricing_Plans' ) ) {

	class Ced_Pricing_Plans {

		public function ced_pricing_plan_display() {

			$ced_contract_details = get_option( 'ced_unified_contract_details', array() );
			$contract_id          = isset( $ced_contract_details['amazon'] ) && isset( $ced_contract_details['amazon']['contract_id'] ) ? $ced_contract_details['amazon']['contract_id'] : '';

			$currentPlan = array();

			if ( !empty( $contract_id ) ) {

				require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-billing-apis.php';

				$ced_billing_instance  = new Billing_Apis();
				$current_plan_response = $ced_billing_instance->getAmazonPlanById( );

				if ( isset( $current_plan_response['status'] ) && $current_plan_response['status'] ) {

					$responseBody = isset( $current_plan_response['data'] ) && isset( $current_plan_response['data'][0] ) ? $current_plan_response['data'][0] : array() ;
					if ( !empty( $responseBody ) ) {
						$subscriptionStatus = isset( $responseBody['status'] ) ? $responseBody['status'] : '';
					} else {
						$subscriptionStatus = '';
					}

					$woo_response = isset( $responseBody['data'] ) ? $responseBody['data'] : '{}';
					$woo_response = json_decode( $woo_response, true );

					$billing_intents = isset( $woo_response['billing_intents'] ) ? $woo_response['billing_intents'] : array();

					if ( ! empty( $billing_intents ) ) {

						$completedIntents = array_filter(
							$billing_intents,
							function ( $intent ) {
								return 'completed' === $intent['status'];
							}
						);

						usort(
							$completedIntents,
							function ( $a, $b ) {
								return $a['id'] - $b['id'];
							}
						);

						$len         = count( $completedIntents ) - 1;
						$currentPlan = $completedIntents[ $len ]['payload'];

						$currentPlan['contract_id']       = $contract_id;
						$currentPlan['plan_status']       = isset( $responseBody['status'] ) ? $responseBody['status'] : '' ;
						$currentPlan['next_payment_date'] = isset( $woo_response['next_payment_date'] ) ? $woo_response['next_payment_date'] : '' ;
						$currentPlan['end_date']          = isset( $woo_response['end_date'] ) ? $woo_response['end_date'] : '' ;
						// $currentPlan['price']             = isset( $woo_response['price'] ) ? $woo_response['price'] : '' ;

						
						// if ( empty($_GET['billing_period']) && isset( $subscribedPlan['billing_period'] ) ) {
						// 	$billing_period = $subscribedPlan['billing_period'] ;
						// }
						

					}

				}
				

			}

		
			$plan_type    = ! empty( $_GET['plan_type'] ) ? sanitize_text_field( wp_unslash( $_GET['plan_type'] ) ) : 'monthly';
			$is_update    = ! empty( $_GET['is_update'] ) ? sanitize_text_field( wp_unslash( $_GET['is_update'] ) ) : 'no';
			$product_plan = ! empty( $_GET['product_plan'] ) ? sanitize_text_field( wp_unslash( $_GET['product_plan'] ) ) : '';

			$plan_type = isset( $_GET['plan_type'] ) ? sanitize_text_field( $_GET['plan_type'] ) : 'monthly';
			$plan_data = $this->ced_get_pricing_plan();

			$prod_data  = isset( $plan_data['plan_data'] ) ? $plan_data['plan_data'] : array();
			$trial_desc = isset( $plan_data['trial_desc'] ) ? $plan_data['trial_desc'] : '';
			

			// get all plans
			if ( ! empty( $prod_data ) ) {

				$subscriptionVerified = 0;
				$plan_name            = '';

				// check the subscription is verified or not
				$subscriptionStatus   = '';
				$subscribtion_details = array();

				if ( is_array( $currentPlan ) && ! empty( $currentPlan ) ) {


					$plan_data         = $currentPlan;
					$status_is         = ! empty( $plan_data['plan_status'] ) ? $plan_data['plan_status'] : '';
					$next_payment_date = ! empty( $plan_data['next_payment_date'] ) ? $plan_data['next_payment_date'] : '';
					$end_date          = ! empty( $plan_data['end_date'] ) ? $plan_data['end_date'] : '';

					$plan_name      = ! empty( $plan_data['name'] ) ? $plan_data['name'] : '';
					$price          = ! empty( $plan_data['price'] ) ? $plan_data['price'] : '';
					$billing_period = ! empty( $plan_data['billing_period'] ) ? $plan_data['billing_period'] : '';
					$contract_id    = ! empty( $plan_data['contract_id'] ) ? $plan_data['contract_id'] : '';

					$subscribtion_details['name']              = $plan_name;
					$subscribtion_details['billing_period']    = $billing_period;
					$subscribtion_details['status']            = $status_is;
					$subscribtion_details['end_date']          = $end_date;
					$subscribtion_details['next_payment_date'] = $next_payment_date;
					$subscribtion_details['price']             = $price;




					$cancelled   = '';
					$status_html = '';
					if ( 'active' === $status_is ) {
						$status_html = "<span style='color:green'>Active</span>";
					} elseif ( 'pending' === $status_is ) {
						$status_html = "<span style='color:yellow'>Pending</span>";
					} elseif ( 'canceled' === $status_is ) {
						$status_html = "<span style='color:red'>Canceled</span>";
					}

					if ( ! empty( $end_date ) ) {
						$current_date = strtotime( gmdate( 'Y-m-d h:i:s' ) );
						$end_date_str = strtotime( $end_date );
						if ( $current_date >= $end_date_str ) {
							$next_html = '<p style="margin-top: 20px;">Current plan is already expired on <span style="color:red;"><b>' . $end_date . '</b></span>. Please Renew it to continue the services.</p>';
						} else {
							$next_html = '<p style="margin-top: 20px;">Current plan is expiring on <span style="color:red;"><b>' . $end_date . '</b></span>. Please Renew it to continue the services.</p>';
						}
					} elseif ( 'canceled' == $status_is ) {
						$cancelled = 'disabled';
						$next_html = '<p style="margin-top: 20px;">Current plan is expiring on <span style="color:red;"><b>' . $next_payment_date . '</b></span>. Please Renew it to continue the services.</p>';
					} elseif ( ! empty( $next_payment_date ) ) {
						$next_html = '<p style="margin-top: 20px;">Your next subscription payment is scheduled on <span style="color:green;"><b>' . $next_payment_date . '</b></span>.</p>';
					} else {
						$next_html = '';
					}

					$subscriptionStatus = $status_is;


					if ( 'active' == $status_is  ) {
						$subscriptionVerified = 1;
					}

				}

				// new client || update plan case ||  subscription not verified  ==> display all plans
				if ( 'yes' == $is_update || ! $subscriptionVerified ) {

					// subscription  not verified;
					if ( 'pending' == $subscriptionStatus ) {

						?>

						<div class="ced-error ced_pending_checkout_container" >
							<p><?php echo esc_html__( 'You have a pending transaction. Please proceed to ', 'amazon-for-woocommerce' ); ?>
							<a href="#" class="btn btn-primary text-uppercase woo_ced_plan_selection_button" data-plan_name="<?php echo esc_attr( $plan_name ); ?>"  
							data-contract_id="<?php echo esc_attr( $contract_id ); ?>" ><?php echo esc_html__( 'checkout', 'amazon-for-woocommerce' ); ?></a> </p>
						</div>

						<?php
					}

					if ( 'paused' == $subscriptionStatus ) {
						?>
						<div class="ced-error ced_pending_checkout_container" >
							<p><?php echo esc_html__( "You don't currently have an active plan. Please choose a plan to proceed.", 'amazon-for-woocommerce' ); ?></p>
						</div>
						<?php
					}

					?>

					<div class="ced-pricing-plan-card-holder">

						<div class="switch-wrapper-container">
							<div class="switch-wrapper">
								<input id="monthly" type="radio" name="switch" value="monthly" 
									<?php
									if ( 'monthly' == $plan_type ) {
										echo 'checked'; }
									?>
								>
								<input id="yearly" type="radio" name="switch" value="yearly" 
									<?php
									if ( 'yearly' == $plan_type ) {
										echo 'checked'; }
									?>
									>
								<label for="monthly">Monthly</label>
								<label for="yearly">Yearly</label>
								<span class="highlighter"></span>
							</div>

						</div>

						<div class="ced-pricing-card-holder">
							<div class="ced-pricing-card-wrapper">
								
								<?php

								foreach ( $prod_data as $key => $plan ) {
									
									echo '<div class="ced-pricing-card">
                                            <div class="ced-pricing-card-content">
                                                <div class="ced-pricing-content">
                                                    <h3>' . esc_attr( $plan['plan_name'] ) . '</h3>
                                                    <h4><span class="ced-price-value">$' . esc_attr( $plan['plan_price'] ) . '</span>/month</h4>';
									if ( 'yearly' === $plan_type ) {
										echo '<i>* billed annually</i>';
									}
												echo '</div>
                                                <div class="ced-pricing-list-text">
                                                    <ol>';
														$desc = explode( ',', $plan['plan_description'] );
									if ( is_array( $desc ) && ! empty( $desc ) ) {
										foreach ( $desc as $k => $v ) {
											echo '<li>' . esc_attr( $v ) . '</li>';
										}
									}
													echo '</ol>
                                                </div>
                                                <div class="ced-pricing-select">
                                                    <div class="ced-pricing-select-button">
                                                        <a href="#" class="btn btn-primary text-uppercase woo_ced_plan_selection_button" data-plan_name="' . esc_attr( $plan['plan_name'] ) . '" data-plan_cost="' . esc_attr( $plan['price_total'] ) . '" 
                                                        data-contract_id="';

									if ( isset( $plan_data['plan_status'] ) && 'canceled' !== $plan_data['plan_status'] ) {
										echo esc_attr( $contract_id ); }

														echo '">Select plan</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
								}

								?>

							</div>        
						</div>    
								

					</div>
					
					<div id="free_trial_notice" class="ced-center"><span><?php echo esc_html__( $trial_desc, 'amazon-for-woocommerce' ); ?></span></div>
				   
					<?php
				} else {

					// subscription is verified  and display current plan
					?>

				
					<!-- new card starts -->

					<div class="ced-pricing-plan-wrapper">
					<div class="ced-pricing-plan-wrap">
						<div class="ced-pricing-plan-container">
							<div class="ced-pricing-plan-common-wrapper">
								<h2>Pricing Plan Details</h2>
							</div>
							<div class="ced-pricing-plan-details-container">
								<div class="ced-pricing-plan-details-wrap">
									<table>
										<tbody>
											<tr>
												<td>Plan Status</td>
												<td>:</td>
												<td><span class="<?php echo esc_attr($subscriptionStatus); ?>"><?php echo esc_attr(ucfirst( $subscriptionStatus )); ?></span></td>
											</tr>
											<tr>
												<td>Plan Name</td>
												<td>:</td>
												<td><?php echo esc_attr($plan_name) . '/' . esc_attr($billing_period); ?></td>
											</tr>
											<tr>
												<td>Plan Price</td>
												<td>:</td>
												<td><?php echo esc_attr($price); ?></td>
											</tr>
											<tr>
												<td>Next Payment Date</td>
												<td>:</td>
												<td><?php echo esc_attr($next_payment_date); ?></td>
											</tr>
											
										</tbody>
									</table>
								</div>
								<div class="ced-pricing-plan-action-container">
									<div class="ced-pricing-action-buttons">
										<button class="ced-update ced-update-current-plan ced-change-plan">Update</button>
										<?php if ( 'active' == $subscriptionStatus ) : ?>
										<button class="ced-cancel ced-cancel-current-plan ced-cancel-plan">Cancel</button>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>



					<!-- new card ends -->

					<?php

				}
			} else {

				// unable to get all plans
				?>

					<div class="jumbotron ced_subscription_warning" >
						<h1 class="display-4"><?php echo esc_html__( 'Hello, User!', 'amazon-for-woocommerce' ); ?></h1>
						<p class="lead"><?php echo esc_html__( 'At this moment we are unable to load your current plan details, please Refresh the page or contact support.', 'amazon-for-woocommerce' ); ?></p>
						<hr class="my-4">

						<p class="lead">
							<a class="components-button is-primary" href="#" role="button" onclick="history.back()" ><?php echo esc_html__( 'Go Back', 'amazon-for-woocommerce' ); ?></a>
						</p>
					</div>


				<?php
			}
		}


		public function ced_get_pricing_plan() {

			$plan_type = ! empty( $_GET['plan_type'] ) ? sanitize_text_field( wp_unslash( $_GET['plan_type'] ) ) : '';

			if ( empty( $plan_type ) ) {

				$currentPlan = get_option( 'ced_unified_contract_details', array() );
				if ( ! isset( $_GET['is_update'] ) && is_array( $currentPlan ) && ! empty( $currentPlan ) && isset( $currentPlan['amazon'] ) ) {

					$plan_data      = $currentPlan['amazon'];
					$billing_period = ! empty( $plan_data['billing_period'] ) ? $plan_data['billing_period'] : 'monthly';

					$plan_type = $billing_period;

				} else {
					$plan_type = 'monthly';
				}
			}

			$product_data = 'amazon';

			$arrContextOptions = array(
				'ssl'=>array(
					'verify_peer'=>false,
					'verify_peer_name'=>false,
				),
			);  
		  
			$plan_data = wp_safe_remote_get( 'https://api.cedcommerce.com/woobilling/live/ced_pricing_plan_options.json' );
			$plan_data = isset( $plan_data['body'] ) ? $plan_data['body'] : '{}';

			
			$prod_data   = array();
			$contract_id = '';

			if ( ! empty( $plan_data ) ) {
				$plan_data = json_decode( $plan_data, true );
				$prod_data = isset( $plan_data[ $product_data ] ) && isset( $plan_data[ $product_data ][ $plan_type ] ) ? $plan_data[ $product_data ][ $plan_type ] : array();

			}

			return array(
				'plan_data'  => $prod_data,
				'trial_desc' => isset( $plan_data[ $product_data ] ) && isset( $plan_data[ $product_data ]['description'] ) ?  $plan_data[ $product_data ]['description'] : '',
			);
		}
	}
}
