<?php

/**
 * A register and information repository of user capabilities.
 *
 * The class will also handle the registration and mapping of user capabilities.
 *
 * @todo should this be part of the model schema? There are things that might require caps and are NOT models...
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */

namespace SolidAffiliate\Lib;

/**
 * Class Capabilities
 *
 * @since   TBD
 *
 * @package SolidAffiliate\Lib
 */
class Capabilities
{

	/**
	 * This is the highest level capability that will allow you to do anything with the plugin.
	 */
	const MANAGE_AFFILIATE_PROGRAM = 'manage_affiliate_program';

	/**
	 * The capability a user shoudl have to insert and update Affiliates.
	 * I set it to 'list_users' because administators have this capability by default, on BOTH multisite and single site.
	 * Source: https://wordpress.org/documentation/article/roles-and-capabilities/#administrator 
	 * @var string
	 */
	const EDIT_AFFILIATES = 'list_users';
}
