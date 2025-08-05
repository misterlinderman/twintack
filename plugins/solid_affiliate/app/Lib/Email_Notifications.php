<?php

/**
 * Handles email based notifications for a set of the plugin events.
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\Core;
use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\StoreCreditTransaction;

/**
 * Class Email_Notifications
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */
class Email_Notifications
{
	const EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE = 'affiliate_manager-new_affiliate';
	const EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL = 'affiliate_manager-new_referral';
	const EMAIL_AFFILIATE_NEW_REFERRAL = 'affiliate-new_referral';
	const EMAIL_AFFILIATE_APPLICATION_ACCEPTED = 'affiliate-affiliate_application_accepted';

	const ALL_EMAILS = [
		self::EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE,
		self::EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL,
		self::EMAIL_AFFILIATE_NEW_REFERRAL,
		self::EMAIL_AFFILIATE_APPLICATION_ACCEPTED,
	];

	/**
	 * Registers the hooks required by Email Notifications to work.
	 *
	 * @since TBD
	 *
	 * @return bool Whether the email hooks registration was successful or not.
	 */
	public static function register_hooks()
	{
		$notifications = (array) Settings::get(Settings::KEY_EMAIL_NOTIFICATIONS);

		if (in_array(self::EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE, $notifications, true)) {
			add_action('solid_affiliate/Affiliate/new_registration/success', [static::class, 'on_new_affiliate_registration_success'], 10, 2);
			add_action('solid_affiliate/notifications/email/Affiliate/new_registration', [static::class, 'dispatch_affiliate_new_registration_notification'], 10, 3);
		}

		if (count(array_intersect([self::EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL, self::EMAIL_AFFILIATE_NEW_REFERRAL], $notifications))) {
			add_action('solid_affiliate/Referral/update', [static::class, 'on_referral_update'], 10, 2);
			add_action('solid_affiliate/notifications/email/Referral/insertion', [static::class, 'dispatch_referral_insertion_notification'], 10, 2);
		}

		if (in_array(self::EMAIL_AFFILIATE_APPLICATION_ACCEPTED, $notifications, true)) {
			add_action(DevHelpers::AFFILIATE_APPROVED, [static::class, 'on_affiliate_approved'], 10, 2);
			add_action('solid_affiliate/notifications/email/Affiliate/application_approved', [static::class, 'dispatch_affiliate_application_approved_notification']);
		}

		add_action('solid_affiliate/notifications/email/Affiliate/setup_wizard_invite', [static::class, 'dispatch_affiliate_setup_wizard_invite_notification']);


		/////////////////////////////////////////////
		// monthly report email
		if (Settings::get(Settings::KEY_IS_AFFILIATE_MANAGER_MONTHLY_REPORT_EMAIL_ENABLED)) {
			// Schedule the monthly report if not already scheduled
			if (!wp_next_scheduled('solid_affiliate/monthly_report')) {
				// Schedule for first day of next month at 00:00
				$next_month = strtotime('first day of next month midnight');
				// $next_month + 1 day to ensure it runs on the next month not this month.
				$next_month += 86400;
				wp_schedule_event($next_month, 'monthly', 'solid_affiliate/monthly_report');
			}

			add_action('solid_affiliate/monthly_report', [static::class, 'trigger_monthly_report']);
			add_action('solid_affiliate/notifications/email/monthly_report', [static::class, 'dispatch_monthly_report_notification']);
		}
		///////////////////////////////////

		/////////////////////////////////////////////
		// Store Credit related emails - TODO add setting to disable/enable from within the addon
		$is_store_credit_addon_enabled = Core::is_addon_enabled(StoreCreditAddon::ADDON_SLUG);
		$is_store_credit_notification_email_enabled = (bool)Core::get_addon_setting(StoreCreditAddon::ADDON_SLUG, StoreCreditAddon::SETTING_KEY_IS_STORE_CREDIT_TRANSACTION_NOTIFICATION_EMAIL_ENABLED);
		if ($is_store_credit_addon_enabled && $is_store_credit_notification_email_enabled) {
			add_action('solid_affiliate/notifications/email/Affiliate/store_credit_transaction', [static::class, 'dispatch_affiliate_store_credit_transaction_notification']);
		}

		self::handle_disabling_email_notifications_for_specific_affiliates();

		return true;
	}


	/**
	 * Handles the notification of a successful new Affiliate registration.
	 *
	 * @since TBD
	 *
	 * @param int $affiliate_id The new Affiliate ID; this is NOT the same as the user ID.
	 * @param int $user_id      The new affiliate user ID.
	 *
	 * @return array<int> An array of enqueued actions IDs, from the action-scheduler package.
	 */
	public static function on_new_affiliate_registration_success($affiliate_id, $user_id)
	{
		$affiliate_managers = self::get_affiliate_managers();
		if (!count($affiliate_managers)) {
			return [];
		}

		$check = static::check_affiliate_user_ids($affiliate_id, $user_id);
		if ($check->isLeft) {
			return [];
		}

		$batches = array_chunk($affiliate_managers, 3);

		$action_ids = array_map(function ($batch) use ($affiliate_id, $user_id) {
			return Action_Scheduler::enqueue_async_action(
				'solid_affiliate/notifications/email/Affiliate/new_registration',
				['to' => $batch, 'affiliate_id' => $affiliate_id, 'user_id' => $user_id],
				'solid_affiliate/notifications'
			);
		}, $batches);

		// Remove any empty action IDs
		$action_ids = array_filter($action_ids);

		return $action_ids;
	}

	/**
	 * Checks an Affiliate in relation to a user, ensures that they line up.
	 *
	 * @since TBD
	 *
	 * @param int $affiliate_id The Affiliate ID to check.
	 * @param int $user_id      The user ID to check.
	 *
	 * @return Either<list{Affiliate, \WP_User}>
	 */
	protected static function check_affiliate_user_ids($affiliate_id, $user_id)
	{
		$affiliate = Affiliate::for_user_id((int) $user_id);
		$user      = get_user_by('ID', (int) $user_id);

		if (
			$affiliate instanceof Affiliate
			&& ((int) $affiliate->id === (int) $affiliate_id)
			&& ($user instanceof \WP_User)
		) {
			return new Either([''], [$affiliate, $user], true);
		} else {
			/**
			 * TODO this is an absolute hack. Either is made in a dumb way, when a Left is returned
			 *   it still expects the "right" values to be of the appropriate type. Need to fix this.
			 * @var Affiliate $affiliate
			 * @var \WP_User $user
			 */
			return new Either([__('Something went wrong.', 'solid-affiliate')], [$affiliate, $user], false);
		}
	}

	/**
	 * Dispatches the email notifications for a new Affiliate registration to each Affiliate Manager.
	 *
	 * @since TBD
	 *
	 * @param array<string>|string $to           A list of email destination addresses.
	 * @param int           $affiliate_id The new Affiliate ID in the affiliates table.
	 * @param int           $user_id      The new Affiliate user ID.
	 *
	 * @return array<string> A list of addresses the email was sent to.
	 */
	public static function dispatch_affiliate_new_registration_notification($to, $affiliate_id, $user_id)
	{
		$check = static::check_affiliate_user_ids($affiliate_id, $user_id);
		if ($check->isLeft) {
			return [];
		}

		list($affiliate, $user) = $check->right;

		$subject          = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_AFFILIATE);
		$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_AFFILIATE);

		$email_template_vars = Templates::email_template_vars_for_affiliate($affiliate, $user);
		$message = Templates::render($message_template, $email_template_vars);

		return static::send($to, $subject, $message);
	}

	/**
	 * Unregisters the filters managed by the class for the current request.
	 *
	 * @since TBD
	 *
	 *
	 * @return void  This class will unhook the filters.
	 */
	public static function unregister()
	{
		// This list is manually curated, keep it up-to-date as new filters are added/removed.
		$filters = [
			['solid_affiliate/Affiliate/new_registration/success', [static::class, 'on_new_affiliate_registration_success'], 10],
			['solid_affiliate/notifications/email/Affiliate/new_registration', [static::class, 'dispatch_affiliate_new_registration_notification'], 10],
			['solid_affiliate/Referral/update', [static::class, 'on_referral_update'], 10],
			['solid_affiliate/Referral/insert', [static::class, 'on_referral_insert'], 10],
			['solid_affiliate/notifications/email/Referral/insertion', [static::class, 'dispatch_referral_insertion_notification'], 10],
			[DevHelpers::AFFILIATE_APPROVED, [static::class, 'on_affiliate_approved'], 10],
			['solid_affiliate/notifications/email/Affiliate/application_approved', [static::class, 'dispatch_affiliate_application_approved_notification'], 10],
			['solid_affiliate/monthly_report', [static::class, 'trigger_monthly_report'], 10],
			['solid_affiliate/notifications/email/monthly_report', [static::class, 'dispatch_monthly_report_notification'], 10]
		];

		foreach ($filters as list($tag, $function, $priority)) {
			if (has_filter($tag, $function)) {
				remove_filter($tag, $function, $priority);
			}
		}
	}

	/**
	 * When a Referral is updated, notify the Affiliate and Affiliate Manager, if required, of the Referral status.
	 *
	 * @since TBD
	 *
	 * @param int       $referral_id The ID of the just updated Referral.
	 * @param Referral $previous     An Referral model instance representing the Referral status before the update.
	 *
	 * @return array<int> An array of the enqueued email dispatch actions IDs.
	 */
	public static function on_referral_update($referral_id, $previous)
	{
		$check = static::check_referral_id($referral_id);
		if ($check->isLeft) {
			return [];
		}

		list($referral, $_affiliate, $user) = $check->right;

		/** @psalm-suppress DocblockTypeContradiction */
		if (!($referral instanceof Referral)) {
			return [];
		}

		$referral_status = $referral->status;

		if (!($referral_status === Referral::STATUS_UNPAID && $previous->status !== $referral_status)) {
			return [];
		}


		$notifications = (array) Settings::get(Settings::KEY_EMAIL_NOTIFICATIONS);
		if (in_array(self::EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL, $notifications, true)) {
			// If enabled, notify the Affiliate Manager.
			$recipients = self::get_affiliate_managers();
		} else {
			$recipients = [];
		}


		$should_send_to_affiliate = (bool)apply_filters("solid_affiliate/notifications/email/Referral/should_send_to_affiliate", true, $_affiliate->id);
		if ($should_send_to_affiliate && in_array(self::EMAIL_AFFILIATE_NEW_REFERRAL, $notifications, true)) {
			// If enabled, notify the Affiliate as well.
			$recipients[] = $user->user_email;
		}

		if (!count($recipients)) {
			return [];
		}

		$batches = array_chunk($recipients, 3);

		$action_ids = array_map(function ($batch) use ($referral_id) {
			return Action_Scheduler::enqueue_async_action(
				'solid_affiliate/notifications/email/Referral/insertion',
				['to' => $batch, 'referral_id' => $referral_id],
				'solid_affiliate/notifications'
			);
		}, $batches);

		// Remove any empty action IDs
		$action_ids = array_filter($action_ids);

		return $action_ids;
	}

	/**
	 * Handles the notification of a successful new Referral creation.
	 *
	 * @since TBD
	 *
	 * @param int $referral_id The new Referral ID.
	 *
	 * @return array<int> An array of enqueued actions IDs, from the action-scheduler package.
	 */
	public static function on_referral_insert($referral_id)
	{
		$check = static::check_referral_id($referral_id);
		if ($check->isLeft) {
			return [];
		}

		list($_referral, $_affiliate, $user) = $check->right;

		$recipients = self::get_affiliate_managers();


		$notifications = (array) Settings::get(Settings::KEY_EMAIL_NOTIFICATIONS);
		$should_send_to_affiliate = (bool)apply_filters("solid_affiliate/notifications/email/Referral/should_send_to_affiliate", true, $_affiliate->id);
		if ($should_send_to_affiliate && in_array(self::EMAIL_AFFILIATE_NEW_REFERRAL, $notifications, true)) {
			// If enabled, notify the Affiliate as well.
			$recipients[] = $user->user_email;
		}

		if (!count($recipients)) {
			return [];
		}

		$batches = array_chunk($recipients, 3);

		$action_ids = array_map(function ($batch) use ($referral_id) {
			return Action_Scheduler::enqueue_async_action(
				'solid_affiliate/notifications/email/Referral/insertion',
				['to' => $batch, 'referral_id' => $referral_id],
				'solid_affiliate/notifications'
			);
		}, $batches);

		// Remove any empty action IDs
		$action_ids = array_filter($action_ids);

		return $action_ids;
	}

	/**
	 * Dispatches the email notifications for a new Referral creation to each Affiliate Manager.
	 *
	 * @since TBD
	 *
	 * @param array<string>|string $recipients  A list of email destination addresses.
	 * @param int                  $referral_id
	 *
	 * @return array<string> A list of addresses the email was sent to.
	 */
	public static function dispatch_referral_insertion_notification($recipients, $referral_id)
	{
		$recipients = is_array($recipients) ? $recipients : array($recipients);

		$check = static::check_referral_id($referral_id);

		if ($check->isLeft) {
			return [];
		}

		list($referral, $affiliate, $user) = $check->right;

		$affiliate_managers = array_diff($recipients, [$user->user_email]);
		$affiliate_email = array_intersect($recipients, [$user->user_email]);
		$sent = [];

		$affiliate_template_vars = Templates::email_template_vars_for_affiliate($affiliate, $user);
		$referral_template_vars = Templates::email_template_vars_for_referral($referral);
		$email_template_vars = array_merge($affiliate_template_vars, $referral_template_vars);

		if (count($affiliate_managers)) {
			$subject          = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_REFERRAL);
			$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_REFERRAL);

			$message = Templates::render($message_template, $email_template_vars);

			$sent[] = static::send($affiliate_managers, $subject, $message);
		}

		if (count($affiliate_email)) {
			$subject          = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_REFERRAL);
			$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_REFERRAL);

			$message = Templates::render($message_template, $email_template_vars);

			$sent[] = static::send($affiliate_email, $subject, $message);
		}

		return count($sent) ? array_merge(...$sent) : [];
	}

	/**
	 * Returns a list of the current Affiliate manager email addresses.
	 *
	 * @since TBD
	 *
	 * @return array<string> A list of the current Affiliate Managers email addresses.
	 */
	public static function get_affiliate_managers()
	{
		$affiliate_managers = array_filter(explode(' ', (string) Settings::get(Settings::KEY_AFFILIATE_MANAGER_EMAIL)));

		return (array)$affiliate_managers;
	}

	/**
	 * Checks an Referral is coherent in its relation with an Affiliate and a user.
	 *
	 * @since TBD
	 *
	 * @param int $referral_id The Referral ID to check.
	 *
	 * @return Either<array{0: Referral, 1: Affiliate, 2: \WP_User}>
	 */
	public static function check_referral_id($referral_id)
	{
		$referral = Referral::find($referral_id);
		if (!$referral instanceof Referral) {
			/** @var array{0: Referral, 1: Affiliate, 2: \WP_User} $stub*/ // TODO mega hack because of the Either issue.
			$stub = null;
			return new Either([__('Referral not found for provided ID', 'solid-affiliate')], $stub, false);
		}
		$affiliate_id = $referral->affiliate_id;
		$affiliate    = Affiliate::find($affiliate_id);
		if (!$affiliate instanceof Affiliate) {
			/** @var array{0: Referral, 1: Affiliate, 2: \WP_User} $stub*/
			$stub = null;
			return new Either([__('Affiliate not found for provided ID', 'solid-affiliate')], $stub, false);
		}
		$user = get_user_by('ID', $affiliate->user_id);
		if (!$user instanceof \WP_User) {
			/** @var array{0: Referral, 1: Affiliate, 2: \WP_User} $stub*/
			$stub = null;
			return new Either([__('User not found for provided ID', 'solid-affiliate')], $stub, false);
		}

		/** @psalm-suppress InvalidReturnStatement */
		return new Either([''], [$referral, $affiliate, $user], true);
	}

	/**
	 * Sends an email to a group of recipients.
	 *
	 * @since TBD
	 *
	 * @param string|array<string> $recipients The recipients of the email.
	 * @param string               $subject    The email subject.
	 * @param string               $message    The email body.
	 *
	 * @return array<string> A list of recipients the email was successfully dispatched to.
	 */
	public static function send($recipients, $subject, $message)
	{
		$headers = [];

		if ('plaintext' !== Settings::get(Settings::KEY_EMAIL_TEMPLATE)) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		$from_name  = trim((string)Settings::get(Settings::KEY_EMAIL_FROM_NAME));
		$from_email = trim((string)Settings::get(Settings::KEY_FROM_EMAIL));
		if ($from_name && filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
			$headers[] = "From: {$from_name} <{$from_email}>";
		}

		$sent = [];

		foreach ((array) $recipients as $recipient) {
			SolidLogger::log("Sending notification email to {$recipient} with subject {$subject}");

			if (!wp_mail($recipient, $subject, $message, $headers)) {
				// Log a notice for the admin that email could not be sent to the Affiliate Manager.
				// TODO add Log API with type-hints.
				// e.g. Log::error()
				do_action(
					'solid_affiliate/log',
					'error',
					__CLASS__,
					'Failed to send notification email.',
					['to' => $recipient, 'subject' => $subject]
				);

				SolidLogger::log("Failed to send notification email to {$recipient} with subject {$subject}");
			} else {
				$sent[] = $recipient;
			}
		}

		return $sent;
	}

	/**
	 * Checks an affiliate by ID to make sure it's coherent with a User.
	 *
	 * @since TBD
	 *
	 * @param int $affiliate_id The ID of the Affiliate to check.
	 *
	 * @return Either<list{Affiliate, \WP_User}>
	 */
	protected static function check_affiliate_id($affiliate_id)
	{
		$affiliate = Affiliate::find($affiliate_id);
		$user      = get_user_by('ID', $affiliate instanceof Affiliate ? $affiliate->user_id : 0);

		if ($affiliate instanceof Affiliate && $user instanceof \WP_User) {
			return new Either([''], [$affiliate, $user], true);
		} else {
			/**
			 * @var Affiliate $affiliate
			 * @var \WP_User $user
			 * TODO another bad Either hack
			 */
			return new Either([''], [$affiliate, $user], false);
		}
	}

	/**
	 * When an Affiliate is updated, notify the Affiliate, if required, of the approval status.
	 *
	 * @since TBD
	 *
	 * @param int       $affiliate_id The ID of the just updated Affiliate.
	 *
	 * @return array<int> An array of the enqueued email dispatch actions IDs.
	 */
	public static function on_affiliate_approved($affiliate_id)
	{
		$check = static::check_affiliate_id($affiliate_id);

		if (!($check->isRight)) {
			return [];
		}

		$action_id = Action_Scheduler::enqueue_async_action(
			'solid_affiliate/notifications/email/Affiliate/application_approved',
			['affiliate_id' => $affiliate_id],
			'solid_affiliate/notifications'
		);

		return [$action_id];
	}

	/**
	 * When an Affiliate is added during setup wizard, notify the Affiliate.
	 *
	 * @since TBD
	 *
	 * @param int       $affiliate_id The ID of the just updated Affiliate.
	 *
	 * @return array<int> An array of the enqueued email dispatch actions IDs.
	 */
	public static function enqueue_setup_wizard_affiliate_welcome_email($affiliate_id)
	{
		$check = static::check_affiliate_id($affiliate_id);

		if (!($check->isRight)) {
			return [];
		}

		$action_id = Action_Scheduler::enqueue_async_action(
			'solid_affiliate/notifications/email/Affiliate/setup_wizard_invite',
			['affiliate_id' => $affiliate_id],
			'solid_affiliate/notifications'
		);

		return [$action_id];
	}

	/**
	 * Dispatches an email notification when an Affiliate is created during setup wizard.
	 *
	 * @since TBD
	 *
	 * @param int $affiliate_id The ID of the Affiliate to send the notification for.
	 *
	 * @return array<string> A list of email addresses the notification was dispatched to.
	 */
	public static function dispatch_affiliate_setup_wizard_invite_notification($affiliate_id)
	{
		$check = static::check_affiliate_id($affiliate_id);
		if ($check->isLeft) {
			return [];
		}

		list($affiliate, $user) = $check->right;

		$subject = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_SETUP_WIZARD_INVITE);
		$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_SETUP_WIZARD_INVITE);

		$affiliate_template_vars = Templates::email_template_vars_for_affiliate($affiliate, $user);
		$message          = Templates::render($message_template, $affiliate_template_vars);

		return static::send($user->user_email, $subject, $message);
	}

	/**
	 * Dispatches an email notification when an Affiliate is approved.
	 *
	 * @since TBD
	 *
	 * @param int $affiliate_id The ID of the Affiliate to send the notification for.
	 *
	 * @return array<string> A list of email addresses the notification was dispatched to.
	 */
	public static function dispatch_affiliate_application_approved_notification($affiliate_id)
	{
		$check = static::check_affiliate_id($affiliate_id);
		if ($check->isLeft) {
			return [];
		}

		list($affiliate, $user) = $check->right;

		$subject          = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_APPLICATION_ACCEPTED);
		$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_APPLICATION_ACCEPTED);
		$affiliate_template_vars = Templates::email_template_vars_for_affiliate($affiliate, $user);
		$message          = Templates::render($message_template, $affiliate_template_vars);

		return static::send($user->user_email, $subject, $message);
	}

	/**
	 * @return string
	 */
	public static function get_email_template_affiliate_manager_new_affiliate()
	{
		if (defined('SOLID_AFFILIATE_DIR')) {
			return file_get_contents(
				SOLID_AFFILIATE_DIR . '/assets/default_email_templates/affiliate_manager_new_affiliate.html'
			);
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public static function get_email_template_affiliate_manager_new_referral()
	{
		if (defined('SOLID_AFFILIATE_DIR')) {
			return file_get_contents(
				SOLID_AFFILIATE_DIR . '/assets/default_email_templates/affiliate_manager_new_referral.html'
			);
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public static function get_email_template_affiliate_application_accepted()
	{
		if (defined('SOLID_AFFILIATE_DIR')) {
			return file_get_contents(
				SOLID_AFFILIATE_DIR . '/assets/default_email_templates/affiliate_application_accepted.html'
			);
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public static function get_email_template_affiliate_new_referral()
	{
		if (defined('SOLID_AFFILIATE_DIR')) {
			return file_get_contents(
				SOLID_AFFILIATE_DIR . '/assets/default_email_templates/affiliate_new_referral.html'
			);
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public static function get_email_template_affiliate_new_store_credit()
	{
		if (defined('SOLID_AFFILIATE_DIR')) {
			return file_get_contents(
				SOLID_AFFILIATE_DIR . '/assets/default_email_templates/affiliate_new_store_credit.html'
			);
		} else {
			return '';
		}
	}

	///////////////////////////////////////////////////////////
	// Monthly Report
	///////////////////////////////////////////////////////////
	/**
	 * Triggers the monthly report email notification.
	 *
	 * @since TBD
	 *
	 * @return array<int> An array of enqueued actions IDs.
	 */
	public static function trigger_monthly_report()
	{
		if (!Settings::get(Settings::KEY_IS_AFFILIATE_MANAGER_MONTHLY_REPORT_EMAIL_ENABLED)) {
			return [];
		}

		$affiliate_managers = self::get_affiliate_managers();
		if (!count($affiliate_managers)) {
			return [];
		}

		$batches = array_chunk($affiliate_managers, 3);

		$action_ids = array_map(function ($batch) {
			return Action_Scheduler::enqueue_async_action(
				'solid_affiliate/notifications/email/monthly_report',
				['to' => $batch],
				'solid_affiliate/notifications'
			);
		}, $batches);

		return array_filter($action_ids);
	}

	/**
	 * Dispatches the monthly report email notification.
	 *
	 * @since TBD
	 *
	 * @param array<string>|string $recipients A list of email destination addresses.
	 *
	 * @return array<string> A list of addresses the email was sent to.
	 */
	public static function dispatch_monthly_report_notification($recipients)
	{
		$recipients = is_array($recipients) ? $recipients : array($recipients);

		// Get the report data for the previous month
		$start_date = strtotime('first day of last month midnight');
		$end_date = strtotime('last day of last month 23:59:59');

		$preset_date_range_params = new PresetDateRangeParams(['preset_date_range' => 'last_month']);
		$data = AdminReportsHelper::_overview_data($preset_date_range_params->computed_start_date(), $preset_date_range_params->computed_end_date(), 0);

		$email_vars = [
			'report_month' => date('F', $start_date),
			'report_year' => date('Y', $start_date),
			'generated_revenue_last_30_days' => $data['referral_amount'],
			'commission_amount' => $data['commission_amount'],
			'net_revenue_amount' => $data['net_revenue'],
			'new_affiliates_last_month' => $data['affiliate_registrations'],
			'total_visits_last_month' => $data['visits'],
			'total_referrals_last_month' => $data['referrals'],
			'dashboard_url' => \SolidAffiliate\Lib\URLs::dashboard_path(false),
		];

		$subject = __('Monthly Report for your affiliate progam', 'solid-affiliate');
		$message_template = self::get_email_template_monthly_report();
		$message = Templates::render($message_template, $email_vars);

		return static::send($recipients, $subject, $message);
	}

	/**
	 * Gets the default monthly report email template.
	 *
	 * @return string
	 */
	public static function get_email_template_monthly_report()
	{
		if (defined('SOLID_AFFILIATE_DIR')) {
			return file_get_contents(
				SOLID_AFFILIATE_DIR . '/assets/default_email_templates/monthly_report.html'
			);
		}
		return '';
	}



	/**
	 * Undocumented function
	 *
	 * @param self::EMAIL_* $email_key
	 * 
	 * @return string[] TODO error handling and proper response and such
	 */
	public static function send_test_email($email_key)
	{
		switch ($email_key) {
			case Email_Notifications::EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE:
				$subject = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_AFFILIATE);
				$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_AFFILIATE);
				break;
			case Email_Notifications::EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL:
				$subject = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_REFERRAL);
				$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_REFERRAL);
				break;
			case Email_Notifications::EMAIL_AFFILIATE_APPLICATION_ACCEPTED:
				$subject = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_APPLICATION_ACCEPTED);
				$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_APPLICATION_ACCEPTED);
				break;
			case Email_Notifications::EMAIL_AFFILIATE_NEW_REFERRAL:
				$subject = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_REFERRAL);
				$message_template = (string)Settings::get(Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_REFERRAL);
				break;
		}

		////////////////////////////////////////////////
		$affiliate_template_vars = Templates::stub_template_vars_for_affiliate();
		$referral_template_vars = Templates::stub_template_vars_for_referral();
		$email_template_vars = array_merge($affiliate_template_vars, $referral_template_vars);
		$message = Templates::render($message_template, $email_template_vars);
		$recipients = Email_Notifications::get_affiliate_managers();

		$subject = 'TEST - ' . $subject;
		$recipients = Email_Notifications::send($recipients, $subject, $message);
		return $recipients;
	}


	/**
	 * @param int|array<int> $store_credit_transaction_ids
	 * 
	 * @return array<int> An array of the enqueued email dispatch actions IDs.
	 */
	public static function async_email_store_credit_transaction_notification($store_credit_transaction_ids)
	{
		$store_credit_transaction_ids = Validators::array_of_int($store_credit_transaction_ids);

		$action_ids = array_map(function ($transaction_id) {
			return Action_Scheduler::enqueue_async_action(
				'solid_affiliate/notifications/email/Affiliate/store_credit_transaction',
				['store_credit_transaction_id' => $transaction_id],
				'solid_affiliate/notifications'
			);
		}, $store_credit_transaction_ids);

		return $action_ids;
	}

	/**
	 * Dispatches an email notification when a Store Credit Transaction is created manually.
	 *
	 * @since TBD
	 *
	 * @param int $store_credit_transaction_id The ID of the Store Credit Transaction to send the notification for.
	 *
	 * @return array<string> A list of email addresses the notification was dispatched to.
	 */
	public static function dispatch_affiliate_store_credit_transaction_notification($store_credit_transaction_id)
	{
		$transaction = StoreCreditTransaction::find($store_credit_transaction_id);
		if (!($transaction instanceof StoreCreditTransaction)) {
			return [];
		}

		$affiliate_id = $transaction->affiliate_id;
		$check = static::check_affiliate_id($affiliate_id);
		if ($check->isLeft) {
			return [];
		}

		list($affiliate, $user) = $check->right;

		$subject = (string)Core::get_addon_setting(StoreCreditAddon::ADDON_SLUG, StoreCreditAddon::SETTING_KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_STORE_CREDIT);
		$message_template = (string)Core::get_addon_setting(StoreCreditAddon::ADDON_SLUG, StoreCreditAddon::SETTING_KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_STORE_CREDIT);
		$affiliate_template_vars = Templates::email_template_vars_for_affiliate($affiliate, $user);
		$store_credit_template_vars = Templates::email_template_vars_for_store_credit_transaction($transaction);
		$email_vars = array_merge($affiliate_template_vars, $store_credit_template_vars);

		$message = Templates::render($message_template, $email_vars);

		return static::send($user->user_email, $subject, $message);
	}


	////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * Handle disabling email notifications for a specific Affiliate
	 * @return void
	 */
	public static function handle_disabling_email_notifications_for_specific_affiliates()
	{
		add_filter(
			'solid_affiliate/notifications/email/Referral/should_send_to_affiliate',
			/** 
			 * @param bool $should_send
			 * @param int $affiliate_id
			 */
			function ($should_send, $affiliate_id) {
				$is_disabled = (bool)AffiliateMeta::get_meta_value($affiliate_id, AffiliateMeta::META_KEY_IS_DISABLE_REFERRAL_NOTIFICATION_EMAILS);
				if ($is_disabled) {
					return false;
				} else {
					return $should_send;
				}
			},
			10,
			2
		);
	}
}
