<?php

/**
 * Provide common template functions and abstractions.
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */

namespace SolidAffiliate\Lib;

use DateTime;
use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\StoreCreditTransaction;

/**
 * Class Templates
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 * 
 * @psalm-type TemplateVarsAffiliate = array{
 *   affiliate_email: string, 
 *   affiliate_name: string, 
 *   affiliate_payment_email: string, 
 *   affiliate_status: string, 
 *   view_affiliate_url: string, 
 *   site_portal_url: string, 
 *   default_affiliate_link: string, 
 *   default_affiliate_coupon_code_or_note: string
 * }
 * 
 * @psalm-type TemplateVarsReferral = array{
 *   referral_commission_amount: string,
 *   referral_date: string,
 *   referral_description: string,
 *   referral_order_amount: string,
 *   referral_source: string,
 *   referral_status: string,
 *   view_referral_url: string,
 *   woo_customer_billing_address: string,
 *   woo_customer_email: string,
 *   woo_customer_full_name: string,
 *   woo_customer_phone: string,
 *   woo_customer_shipping_address: string
 * }
 * 
 * @psalm-type TemplateVarsStoreCreditTransaction = array{
 *   store_credit_amount: string,
 *   store_credit_transaction_description: string,
 *   current_store_credit_balance: string
 * }
 * 
 */
class Templates
{

	/**
	 * Renders a string template replacing the specified variables in it.
	 *
	 * Variables that are not specified are NOT replaced.
	 *
	 * @since TBD
	 *
	 * @param string                         $template The string template to render.
	 * @param array<string,string|int|float> $vars     The variables to replace in the template.
	 *
	 * @return string The rendered template.
	 */
	public static function render($template, $vars = [])
	{
		$search = array_map(static function ($name) {
			// @todo maybe make the `{var}` Handlebars-like? `{{var}}`
			return '{' . trim($name) . '}';
		}, array_keys($vars));

		return str_replace($search, $vars, $template);
	}


	/**
	 * @param Affiliate $affiliate
	 * @param \WP_User $user
	 * 
	 * @return TemplateVarsAffiliate
	 */
	public static function email_template_vars_for_affiliate($affiliate, $user)
	{
		return [
			'affiliate_name' => $user->display_name,
			'affiliate_email' => $user->user_email,
			'affiliate_payment_email' => $affiliate->payment_email,
			'affiliate_status' => __($affiliate->status, 'solid-affiliate'),
			'view_affiliate_url' => URLs::edit(Affiliate::class, $affiliate->id),
			'site_portal_url' => URLs::site_portal_url(),
			'default_affiliate_link' => Affiliate::default_affiliate_link_for($affiliate),
			'default_affiliate_coupon_code_or_note' => self::_coupon_code_or_note(Affiliate::maybe_default_coupon($affiliate))
		];
	}

	/**
	 * @return TemplateVarsAffiliate
	 */
	public static function stub_template_vars_for_affiliate()
	{
		return [
			'affiliate_name' => 'John Doe',
			'affiliate_email' => 'john.doe@gmail.com',
			'affiliate_payment_email' => 'john.doe.paypal@gmail.com',
			'affiliate_status' => __(Affiliate::STATUS_APPROVED, 'solid-affiliate'),
			'view_affiliate_url' => URLs::edit(Affiliate::class, 1),
			'site_portal_url' => URLs::site_portal_url(),
			'default_affiliate_link' => URLs::default_affiliate_link_base_url(),
			'default_affiliate_coupon_code_or_note' => 'fake-coupon-code'
		];
	}



	/**
	 * @param Referral $referral
	 * 
	 * @return TemplateVarsReferral
	 */
	public static function email_template_vars_for_referral($referral)
	{
		$maybe_wc_order = function_exists('wc_get_order') ? \wc_get_order($referral->order_id) : null;
		if ($maybe_wc_order instanceof \WC_Order) {
			$woo_customer_full_name = $maybe_wc_order->get_billing_first_name() . ' ' . $maybe_wc_order->get_billing_last_name();
			$woo_customer_email = $maybe_wc_order->get_billing_email();
			$woo_customer_shipping_address = $maybe_wc_order->get_formatted_shipping_address();
			$woo_customer_billing_address = $maybe_wc_order->get_formatted_billing_address();
			$woo_customer_phone = $maybe_wc_order->get_billing_phone();
		} else {
			$woo_customer_full_name = '-';
			$woo_customer_email = '-';
			$woo_customer_shipping_address = '-';
			$woo_customer_billing_address = '-';
			$woo_customer_phone = '-';
		}

		return [
			'referral_order_amount' => Formatters::money($referral->order_amount),
			'referral_commission_amount' => Formatters::money($referral->commission_amount),
			'referral_description' => $referral->description,
			'referral_status' => __($referral->status, 'solid-affiliate'),
			'referral_date' => Formatters::localized_datetime($referral->created_at),
			'referral_source' => __($referral->referral_source, 'solid-affiliate'),
			'view_referral_url' => URLs::edit(Referral::class, $referral->id),
			// WooCommerce tags. Consider extracting out of this function
			'woo_customer_full_name' => $woo_customer_full_name,
			'woo_customer_email' => $woo_customer_email,
			'woo_customer_shipping_address' => $woo_customer_shipping_address,
			'woo_customer_billing_address' => $woo_customer_billing_address,
			'woo_customer_phone' => $woo_customer_phone,
		];
	}

	/**
	 * @return TemplateVarsReferral
	 */
	public static function stub_template_vars_for_referral()
	{
		return [
			'referral_order_amount' => Formatters::money(420.00),
			'referral_commission_amount' => Formatters::money(50.00),
			'referral_description' => 'This is where the referral description will go.',
			'referral_status' => __(Referral::STATUS_UNPAID, 'solid-affiliate'),
			'referral_date' => Formatters::localized_datetime(Formatters::simple_date()),
			'referral_source' => __(Referral::SOURCE_COUPON, 'solid-affiliate'),
			'view_referral_url' => URLs::edit(Referral::class, 1),
			// WooCommerce tags. Consider extracting out of this function
			'woo_customer_full_name' => 'Mike Ho',
			'woo_customer_email' => 'mike.ho@gmail.com',
			'woo_customer_shipping_address' => '7422 Denriz Avenue, Los Angeles, California 90045',
			'woo_customer_billing_address' => '8550 Wall Street, New York, New York 10005',
			'woo_customer_phone' => '3109085555',
		];
	}

	/** 
	 * @param StoreCreditTransaction $store_credit_transaction
	 * 
	 * @return TemplateVarsStoreCreditTransaction
	 */
	public static function email_template_vars_for_store_credit_transaction($store_credit_transaction)
	{
		$balance = StoreCreditAddon::outstanding_store_credit_for_affiliate($store_credit_transaction->affiliate_id);

		return [
			'store_credit_amount' => Formatters::store_credit_amount($store_credit_transaction->amount, $store_credit_transaction->type),
			'store_credit_transaction_description' => $store_credit_transaction->description,
			'current_store_credit_balance' => Formatters::money($balance)
		];
	}

	/**
	 * @return TemplateVarsStoreCreditTransaction
	 */
	public static function stub_template_vars_for_store_credit_transaction()
	{
		return [
			'store_credit_amount' =>  Formatters::money(420.00),
			'store_credit_transaction_description' => 'Sample description of a store credit transaction.',
			'current_store_credit_balance' => '$1,280.00'
		];
	}

	/**
	 * Undocumented function
	 *
	 * @param \WC_Coupon|null $maybe_coupon
	 *
	 * @return string
	 */
	private static function _coupon_code_or_note($maybe_coupon)
	{
		if (is_null($maybe_coupon)) {
			return __('You have not yet been given a coupon code to share. If you want one please contact the site admin.', 'solid-affiliate');
		} else {
			return $maybe_coupon->get_code();
		}
	}

	/////////////////////////////////////////////////////////////////////////////////////////
	// Template Setting Descriptions
	/////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * @return array<array>
	 */
	public static function tags_to_descriptions()
	{
		$affiliate_tags = [
			'affiliate_name' => __("The display name of the Affiliate, as set on the Affiliate's user profile", 'solid-affiliate'),
			'affiliate_email' => __("The email of the Affiliate.", 'solid-affiliate'),
			'affiliate_payment_email' => __("The payment email of the Affiliate", 'solid-affiliate'),
			'affiliate_status' => __("The current status of the Affiliate", 'solid-affiliate'),
			'view_affiliate_url' => __("The URL to view and edit this affiliate within WordPress admin.", 'solid-affiliate'),
			'site_portal_url' => __("The URL of your site's Affiliate Portal, or the home URL if the 'Affiliate Portal Page' setting is not set.", 'solid-affiliate'),
			'default_affiliate_link' => __("The default affiliate link for your affiliate to send traffic to your site with. The format of this link is configured by Settings > General > URL Tracking Settings > Affiliate Slug Display Format", 'solid-affiliate'),
		];

		$referral_tags = [
			'referral_order_amount' => __("The total amount of the referred order.", 'solid-affiliate'),
			'referral_commission_amount' => __("The amount of commission earned for this Referral.", 'solid-affiliate'),
			'referral_description' => __("Description of the referred order.", 'solid-affiliate'),
			'referral_status' => __("Status of the referral record.", 'solid-affiliate'),
			'referral_date' => __("Date of the referral record.", 'solid-affiliate'),
			'referral_source' => __("Source of the referral record.", 'solid-affiliate'),
			'view_referral_url' => __("The URL to view and edit this Referral within WordPress admin.", 'solid-affiliate'),
		];

		$woocommerce_tags = [
			'woo_customer_full_name' => __("The full name of the customer, if applicable.", 'solid-affiliate'),
			'woo_customer_email' => __("The email of the customer, if applicable.", 'solid-affiliate'),
			'woo_customer_shipping_address' => __("The formatted shipping address of the customer, if applicable.", 'solid-affiliate'),
			'woo_customer_billing_address' => __("The formatted billing address of the customer, if applicable.", 'solid-affiliate'),
			'woo_customer_phone' => __("The phone number of the customer, if applicable.", 'solid-affiliate'),
		];

		$store_credit_tags = [
			'store_credit_amount' => __("The amount of the store credit transaction.", 'solid-affiliate'),
			'store_credit_transaction_description' => __("The description or reason for the store credit transaction", 'solid-affiliate'),
			'current_store_credit_balance' => __("The current balance of total store credit that this Affiliate has", 'solid-affiliate')
		];

		return [
			'Affiliate' => $affiliate_tags,
			'Referral' => $referral_tags,
			'WooCommerce' => $woocommerce_tags,
			'Store Credit Transaction' => $store_credit_tags
		];
	}

	/**
	 * @param string[] $included_tag_groups
	 * @return string
	 */
	public static function tags_to_documentation_html($included_tag_groups = ['Affiliate', 'Referral', 'WooCommerce'])
	{
		// get the mapping of tags to descriptions
		$tags_to_descriptions = self::tags_to_descriptions();

		// filter out by tag group
		/** @psalm-var array<string[]> */
		$tags_to_descriptions = array_intersect_key($tags_to_descriptions, array_flip($included_tag_groups));

		// build the HTML
		return self::tags_to_description_mapping_to_html($tags_to_descriptions);
	}

	/**
	 * @param array<string[]> $tags_to_descriptions_mapping
	 * @return string
	 */
	private static function tags_to_description_mapping_to_html($tags_to_descriptions_mapping)
	{
		$html = "
		<table class='sld-email-tags_table'>
			<tbody>";
		foreach ($tags_to_descriptions_mapping as $tag_group => $tags) {
			$html .= "<tr> <td class='lead' colspan='2'>$tag_group Tags</td> </tr>";
			foreach ($tags as $tag => $description) {
				$html .= "<tr>
							<td>{{$tag}}</td>
							<td>$description</td>
						  </tr>";
			}
		}
		$html .= "
			</tbody>
		</table>";
		return $html;

	}
}
