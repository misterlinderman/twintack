<?php

namespace SolidAffiliate\Lib;

use Exception;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Models\Referral;
use TenQuality\WP\Database\QueryBuilder;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\StoreCreditTransaction;
use WC_Order;

use function Patchwork\Utils\args;

/**
 * @todo: rename this.
 */
trait MikesDataModelTrait
{
	/**
	 * Static constructor that finds recond in database
	 * and fills model.
	 * @since 1.0.0
	 *
	 * @param int $id
	 *
	 * @return self|null
	 */
	public static function find($id)
	{
		if (empty($id)) {
			return null;
		}

		$model = new self([], $id);
		return $model->load();
	}

	/**
	 * Static constructor that finds recond in database
	 * and fills model.
	 * @since 1.0.0
	 *
	 * @param array<int> $id
	 *
	 * @return self[]
	 */
	public static function find_many($ids)
	{
		return self::where(['id' => ['operator' => 'IN', 'value' => $ids]]);
	}


	/**
	 * Static constructor that finds records in database
	 * and fills model using where statement.
	 * @since 1.0.0
	 *
	 * @param array $args Where query statement arguments. See non-static method.
	 *
	 * @return self|null
	 */
	public static function find_where($args)
	{
		$model = new self;
		return $model->load_where($args);
	}


	/**
	 * Deletes
	 * 
	 * Will return and DB errors in the Either->left
	 * TODO N+1
	 *
	 * @param int $id
	 * @param bool $cascade Whether to also delete any child records based on foreign keys.
	 * @global \wpdb $wpdb
	 * 
	 * @return Either<int>
	 */
	public static function delete($id, $cascade = false)
	{
		global $wpdb;
		$class_to_delete = static::class;

		///////////////////////////////////////////////////////////////////////////////////////////
		// TODO this is a hack to remove Affiliate user roles from users when deleting an affiliate.
		if ($class_to_delete === Affiliate::class) {
			Roles::remove_affiliate_role_from_an_affiliates_user($id);
		}
		///////////////////////////////////////////////////////////////////////////////////////////

		// Foreign Keys
		$all_fk_descriptions = [
			[
				'parent_class' => Affiliate::class,
				'child_class' => Visit::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => Referral::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => Payout::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => AffiliateProductRate::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => AffiliateCustomerLink::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => AffiliateMeta::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Visit::class,
				'child_class' => Referral::class,
				'fk' => 'visit_id',
			],
		];

		$fk_descriptions = array_filter($all_fk_descriptions, function ($fk_description) use ($class_to_delete) {
			return ($fk_description['parent_class'] === $class_to_delete);
		});

		foreach ($fk_descriptions as $fk_description) {
			$foreign_key = $fk_description['fk'];
			$child_class = $fk_description['child_class'];
			$child_count = $child_class::count([$foreign_key => $id]);

			if (!empty($child_count)) {
				if ($cascade) {
					$children = $child_class::where([$foreign_key => $id]);
					// TODO N+1
					foreach ($children as $child) {
						$child_class::delete($child->id, true);
					}
				} else {
					$error_msg = "Cannot delete {$class_to_delete} with id={$id}. " . "{$child_count} {$child_class} with {$foreign_key}={$id} exist";
					return new Either([__($error_msg, 'solid-affiliate')], $id, false);
				}
			}
		}


		$is_deleted = $wpdb->delete($wpdb->prefix . static::TABLE, ['id' => $id]);
		$is_deleted = boolval($is_deleted);

		if ($is_deleted) {
			/** @var string $model */
			$model = static::MODEL_NAME;
			/**
			 * Fires after a new model has been deleted.
			 *
			 * @since TBD
			 *
			 * @param int $item_id The new model ID in the model table.
			 */
			do_action("solid_affiliate/{$model}/delete", $id);
			return new Either([''], $id, true);
		} else {
			return new Either([__((string)$wpdb->last_error, 'solid-affiliate')], $id, false);
		}
	}

	/**
	 * Deletes all records which match the where statement.
	 * 
	 * N+1 (because we are using the delete function which only works on one ID at a time)
	 *
	 * @param array $args
	 * @param bool $cascade Whether to also delete any child records based on foreign keys.
	 * 
	 * @return array<Either<int>>
	 */
	public static function delete_where($args = [], $cascade = false)
	{
		$results = self::where($args);

		return array_map(function ($model) use ($cascade) {
			return self::delete($model->id, $cascade);
		}, $results);
	}


	/**
	 * Static constructor that inserts record in database and fills model.
	 * Also optionally sets created_at and updated_at fields to the current time.
	 * @since 1.0.0
	 *
	 * @param array $attributes
	 * @param bool $set_timestamps
	 * @global \wpdb $wpdb
	 * 
	 * note it was return self|null
	 *
	 * @return Either<self>
	 */
	public static function insert($attributes, $set_timestamps = true)
	{
		global $wpdb;
		$class_to_insert = static::class;
		// on insert, we care about the child class
		$all_fk_descriptions = [
			[
				'parent_class' => Affiliate::class,
				'child_class' => Visit::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => Referral::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => Payout::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => AffiliateProductRate::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => StoreCreditTransaction::class,
				'fk' => 'affiliate_id',
			],
			[
				'parent_class' => Affiliate::class,
				'child_class' => AffiliateCustomerLink::class,
				'fk' => 'affiliate_id',
			],
			// [
			// 	'parent_class' => Visit::class,
			// 	'child_class' => Referral::class,
			// 	'fk' => 'visit_id',
			// ],
		];

		$relevent_fk_descriptions = array_filter($all_fk_descriptions, function ($fk_description) use ($class_to_insert) {
			return ($fk_description['child_class'] === $class_to_insert);
		});

		foreach ($relevent_fk_descriptions as $fk_description) {
			$foreign_key = $fk_description['fk'];
			$parent_class = $fk_description['parent_class'];
			$provided_parent_id = $attributes[$foreign_key];
			$parent_count = $parent_class::count(['id' => $provided_parent_id]);

			if (empty($parent_count)) {
				$error_msg = "Cannot insert {$class_to_insert} with {$foreign_key}={$provided_parent_id}. " . "{$parent_class} with id={$provided_parent_id} does not exist";
				return new Either([__($error_msg, 'solid-affiliate')], $provided_parent_id, false);
			}
		}


		///////////////////////////////////////////////////////////////
		// Custom Callbacks on Insert
		// Example: can only create an Affiliate if the user_id if of an actual WP_User
		$all_insert_callbacks = [
			[
				'child_class' => Affiliate::class,
				'error_message_callback' => function ($attributes) {
					$class_to_insert = Affiliate::class;
					$fk = 'user_id';
					$fk_value = $attributes[$fk];
					$parent_class = 'WP_User';
					$error_msg = "Cannot insert {$class_to_insert} with {$fk}={$fk_value}. " . "{$parent_class} with id={$fk_value} does not exist";
					return __($error_msg, 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					$fk = 'user_id';
					$fk_value = $attributes[$fk];
					$maybe_user = get_user_by('id', $fk_value);
					return ($maybe_user instanceof \WP_User);
				}
			],
			[
				'child_class' => Affiliate::class,
				'error_message_callback' => function ($attributes) {
					$error_msg = 'Cannot insert new Affiliates while Solid Affiliate is deactivated or expired. Please activate the plugin to restore proper functionality.';
					return __($error_msg, 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					return License::is_solid_affiliate_activated_and_not_expired_or_on_keyless_free_trial();
				}
			],
			[
				'child_class' => Payout::class,
				'error_message_callback' => function ($attributes) {
					$class_to_insert = Payout::class;
					$fk = 'created_by_user_id';
					$fk_value = $attributes[$fk];
					$parent_class = 'WP_User';
					$error_msg = "Cannot insert {$class_to_insert} with {$fk}={$fk_value}. " . "{$parent_class} with id={$fk_value} does not exist";
					return __($error_msg, 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					$fk = 'created_by_user_id';
					$fk_value = $attributes[$fk];
					$maybe_user = get_user_by('id', $fk_value);
					return ($maybe_user instanceof \WP_User);
				}
			],
			[
				'child_class' => Referral::class,
				'error_message_callback' => function ($attributes) {
					return __("Cannot insert Referral with invalid visit_id and invalid coupon_id.", 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					// Currently we bail on validation if the referral type is manual, subscription renewal, auto referral.
					if (isset($attributes['referral_source'])) {
						$is_manual_referral = Referral::SOURCE_MANUAL == $attributes['referral_source'];
						if ($is_manual_referral) {
							return true;
						}

						$is_auto_referral = Referral::SOURCE_AUTO_REFERRAL == $attributes['referral_source'];
						if ($is_auto_referral) {
							return true;
						}

						$is_affiliate_customer_link = Referral::SOURCE_AFFILIATE_CUSTOMER_LINK == $attributes['referral_source'];
						if ($is_affiliate_customer_link) {
							return true;
						}
					}

					// TODO this should be removed once we clean up Referral data model and remove reliance on "referral_type"
					if (isset($attributes['referral_type'])) {
						$is_auto_referral = Referral::TYPE_AUTO_REFERRAL == $attributes['referral_type'];
						if ($is_auto_referral) {
							return true;
						}
						$is_subscription_renewal = Referral::TYPE_SUBSCRIPTION_RENEWAL == $attributes['referral_type'];
						if ($is_subscription_renewal) {
							return true;
						}
					}

					$is_valid_visit_id = ((int)$attributes['visit_id'] != 0) && (Visit::count(['id' => $attributes['visit_id']]) > 0); // TODO bug here when visit_id is 0
					// TODO it appears that this function is not being called (at least when visit is updated through Admin UI)
					$is_valid_coupon_id = !empty($attributes['coupon_id']); // TODO for now we just check if coupon_id is not 0
					return ($is_valid_visit_id || $is_valid_coupon_id);
				}
			],
			[
				'child_class' => AffiliateProductRate::class,
				'error_message_callback' => function ($attributes) {
					return __("AffiliateProductRate with invalid woocommerce_product_id and invalid affiliate_id. You cannot create multiple AffiliateProductRates for the same affiliate and product combination.", 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					// if there already exists an AffiliateProductRate with the same affiliate_id and woocommerce_product_id, then this is a duplicate
					if (isset($attributes['affiliate_id']) && isset($attributes['woocommerce_product_id'])) {
						$affiliate_id = $attributes['affiliate_id'];
						$woocommerce_product_id = $attributes['woocommerce_product_id'];
						$affiliate_product_rate_count = AffiliateProductRate::count(['affiliate_id' => $affiliate_id, 'woocommerce_product_id' => $woocommerce_product_id]);
						return ($affiliate_product_rate_count == 0);
					} else {
						return false;
					}
				}
			],
			[
				'child_class' => AffiliateProductRate::class,
				'error_message_callback' => function ($attributes) {
					return __("Only one AffiliateProductRate with <strong>Auto Referrals Enabled</strong> can be created <strong>per product</strong>.", 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					// only allow one Auto Referral Enabled AffiliateProductRate per product.
					if (isset($attributes['woocommerce_product_id']) && isset($attributes['is_auto_referral'])) {
						if ($attributes['is_auto_referral']) {
							$woocommerce_product_id = $attributes['woocommerce_product_id'];
							$affiliate_product_rate_count = AffiliateProductRate::count(['woocommerce_product_id' => $woocommerce_product_id, 'is_auto_referral' => true]);
							return ($affiliate_product_rate_count == 0);
						} else {
							return true;
						}
					} else {
						return false;
					}
				}
			],
			// Multiple Store Credit Transaction validations here
			// [x] source and type need to make sense together.
			// [x] source and source_id need to make sense together. (foreign key constraints)
			// [x] affiliate_id needs to actually exist. (foreign key constraints)
			[
				'child_class' => StoreCreditTransaction::class,
				'error_message_callback' => function ($attributes) {
					$type = $attributes['type'];
					$source = $attributes['source'];
					return __("Cannot insert StoreCreditTransaction. The source = $source and type = $type combination is invalid.", 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					if (isset($attributes['type']) && isset($attributes['source'])) {
						$type = $attributes['type'];
						$source = $attributes['source'];
						// valid pairings are:
						// if source is 'manual' then type can be 'credit' or 'debit
						// if source is 'payout' then type can only be 'debit'
						// if source is 'woocommerce_purchase' then type can only be 'credit'
						// if source is 'woocommerce_subscription_renewal' then type can only be 'credit'
						$is_valid_source = ($source == StoreCreditTransaction::SOURCE_MANUAL || $source == StoreCreditTransaction::SOURCE_PAYOUT || $source == StoreCreditTransaction::SOURCE_WOOCOMMERCE_PURCHASE || $source == StoreCreditTransaction::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL);
						$is_valid_type = ($source == StoreCreditTransaction::SOURCE_MANUAL && ($type == StoreCreditTransaction::TYPE_CREDIT || $type == StoreCreditTransaction::TYPE_DEBIT)) ||
							($source == StoreCreditTransaction::SOURCE_PAYOUT && $type == StoreCreditTransaction::TYPE_DEBIT) ||
							($source == StoreCreditTransaction::SOURCE_WOOCOMMERCE_PURCHASE && $type == StoreCreditTransaction::TYPE_CREDIT) ||
							($source == StoreCreditTransaction::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL && $type == StoreCreditTransaction::TYPE_CREDIT);

						// rewrite the logic above to be much easier to read
						return ($is_valid_source && $is_valid_type);
					} else {
						return false;
					}
				}
			],
			[
				'child_class' => StoreCreditTransaction::class,
				'error_message_callback' => function ($attributes) {
					$source = $attributes['source'];
					$source_id = $attributes['source_id'];
					return __("Cannot insert StoreCreditTransaction. The source = $source and source_id = $source_id combination is invalid.", 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					if (isset($attributes['source'])) {
						switch ($attributes['source']) {
							case StoreCreditTransaction::SOURCE_MANUAL:
								return true;
							case StoreCreditTransaction::SOURCE_PAYOUT:
								return Payout::find($attributes['source_id']) != null;
							case StoreCreditTransaction::SOURCE_WOOCOMMERCE_PURCHASE:
								return (\wc_get_order($attributes['source_id']) instanceof \WC_Order);
							case StoreCreditTransaction::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL:
								return (\wc_get_order($attributes['source_id']) instanceof \WC_Order);
							default:
								return false;
						}
					} else {
						return false;
					}
				}
			],
			[
				'child_class' => AffiliateCustomerLink::class,
				'error_message_callback' => function ($attributes) {
					return __("Cannot insert AffiliateCustomerLink with both customer_id and customer_email blank.", 'solid-affiliate');
				},
				'is_valid_callback' => function ($attributes) {
					return ((isset($attributes['customer_id']) && !empty($attributes['customer_id'])) || (isset($attributes['customer_email']) && !empty($attributes['customer_email'])));
				}
			],
		];

		$relevent_insert_callbacks = array_filter($all_insert_callbacks, function ($insert_callback) use ($class_to_insert) {
			return ($insert_callback['child_class'] === $class_to_insert);
		});

		foreach ($relevent_insert_callbacks as $insert_callback) {
			$is_valid = $insert_callback['is_valid_callback']($attributes);

			if (!$is_valid) {
				$error_message = $insert_callback['error_message_callback']($attributes);
				return new Either([$error_message], 0, false);
			}
		}
		// foreach ($relevent_insert_callbacks as $insert_callback) {
		// 	$parent_class = $insert_callback['parent_class_string'];
		// 	$fk = $insert_callback['fk'];
		// 	$fk_value = $attributes[$fk];

		// 	$is_valid = $insert_callback['is_valid_callback']($fk_value);

		// 	if (!$is_valid) {
		// 		$error_msg = "Cannot insert {$class_to_insert} with {$fk}={$fk_value}. " . "{$parent_class} with id={$fk_value} does not exist";
		// 		return new Either([$error_msg], $fk_value, false);
		// 	}
		// }
		// end - Custom Callbacks on Insert
		///////////////////////////////////////////////////////////////

		if ($set_timestamps) {
			$now = current_time('mysql', true);
			$default_timestamps = [
				'created_at' => $now,
				'updated_at' => $now
			];
			$attributes = array_merge($default_timestamps, $attributes);
		}

		$attributes = wp_unslash($attributes);

		$instance = new self($attributes);

		if ($instance->save(true)) {
			/** @var string $model_name */
			$model_name = static::MODEL_NAME;
			/**
			 * Fires after a new model has been inserted.
			 *
			 * @since TBD
			 *
			 * @param int $item_id The new model ID in the model table.
			 */
			do_action("solid_affiliate/{$model_name}/insert", $instance->id);
			$result = new Either([''], $instance, true);
		} else {
			$result = new Either([__((string)$wpdb->last_error, 'solid-affiliate')], $instance, false);
		}


		return $result;
	}

	/**
	 * Insert or update a record.
	 *
	 * @param array<string, mixed> $args
	 * @param boolean $use_defaults
	 * 
	 * @return Either<int> - int is the Item ID.
	 */
	public static function upsert($args = array(), $use_defaults = false)
	{
		$schema = apply_filters("solid_affiliate/upsert/" . self::TABLE . "/schema", self::schema());

		/**
		 * @psalm-suppress MixedAssignment
		 * @psalm-suppress UndefinedFunction
		 * */
		$defaults = $use_defaults ? SchemaFunctions::defaults_from_schema($schema) : ['id' => null];
		/**
		 * @psalm-suppress MixedArgument
		 * */
		$args = wp_parse_args($args, $defaults);
		$args['updated_at'] = current_time('mysql', true);

		$required_fields_errors = array_map(static function ($field) use ($args, $schema) {
			# TODO: Can we have a required bool and let it pass here when it is false?
			if (isset($args[$field]) && Utils::is_zero_number_and_not_considered_empty($schema, $args, $field)) {
				return null;
			}

			if (Utils::is_empty($args[$field])) {
				// translators: the placeholder is the field name.
				$error_message = _x(
					'Required field %s is not provided.',
					'A message to indicate a field value is not valid.',
					'solid-affiliate'
				);

				return sprintf($error_message, $field);
			}

			return null;
		}, self::required_fields());

		$validation_errors = array_map(static function ($field, SchemaEntry $schema_entry) use ($args, $schema) {
			if (!isset($args[$field])) {
				return null;
			} else {
				if ($schema_entry->required) {
					$validate_result = SchemaEntry::validate($schema_entry, $args[$field]);
					if (!$validate_result[0]) {
						// translators: the placeholder is the field name.
						$error_message = _x(
							'Field %s value is not valid.',
							'A message to indicate a field value is not valid.',
							'solid-affiliate'
						);

						return sprintf($error_message, $field);
					}
				} else {
					if (Utils::is_empty($args[$field]) && !Utils::is_zero_number_and_not_considered_empty($schema, $args, $field)) {
						return null;
					} else {
						$validate_result = SchemaEntry::validate($schema_entry, $args[$field]);
						if (!$validate_result[0]) {
							// translators: the placeholder is the field name.
							$error_message = _x(
								'Field %s value is not valid.',
								'A message to indicate a field value is not valid.',
								'solid-affiliate'
							);

							return sprintf($error_message, $field);
						}
					}
				}
			}
		}, array_keys($schema->entries), $schema->entries);

		$error_messages = array_filter(array_merge($required_fields_errors, $validation_errors));

		if (!empty($error_messages)) {
			return new Either($error_messages, 0, false);
		}

		$row_id = (int) $args['id'];
		unset($args['id']);

		// This is to catch any potential empty strings and such being passed in from forms, where
		// we really expect null. In the case of nullable datetime values, this fixes a bug
		// where any resource form submission was setting the datetime to an empty string, which
		// mysql then auto set as the 0:000:000 - 0:0:0 etc default date, but we meant it to be null.
		$nullable_fields = SchemaFunctions::_keys_that_have_prop_true_from_schema($schema, 'nullable');
		foreach ($nullable_fields as $nullable_field) {
			if (isset($args[$nullable_field]) && empty($args[$nullable_field])) {
				$args[$nullable_field] = null;
			}
		}

		/** @var string|array $args_or_error */
		$args_or_error = apply_filters("solid_affiliate/before_upsert/" . self::TABLE, $args);

		if (is_string($args_or_error)) {
			return new Either([$args_or_error], 0, false);
		} else {
			$args = $args_or_error;
		}

		if (!$row_id) {
			$args['created_at'] = current_time('mysql', true);
			$either_item = self::insert($args);
			if ($either_item->isLeft) {
				return new Either($either_item->left, 0, false);
			} else {
				$item_id = (int)$either_item->right->id;
				return new Either([''], $item_id, true);
			}
		} else {
			$instance = self::find($row_id);

			if (is_null($instance)) {
				return new Either([__('Error inserting Model', 'solid-affiliate')], 0, false);
			}

			$eitherUpdate = self::updateInstance($instance, $args);

			if ($eitherUpdate->isRight) {
				return new Either([''], $row_id, true);
			} else {
				return new Either(__($eitherUpdate->left, 'solid-affiliate'), 0, false);
			}
		}
	}

	/**
	 * Update a record.
	 *
	 * @param self $instance
	 * @param array $args
	 * @global \wpdb $wpdb
	 *
	 * @return Either<self>
	 */
	public static function updateInstance($instance, $args = array())
	{
		global $wpdb;

		/**
		 * @psalm-suppress MixedPropertyFetch
		 * @psalm-suppress MixedOperand
		 */
		$table_name = $wpdb->prefix . self::TABLE;
		/**
		 * @psalm-suppress MixedMethodCall
		 */
		if ($wpdb->update($table_name, wp_unslash($args), array('id' => $instance->id))) {
			$new_instance = self::find($instance->id);
			if (is_null($new_instance)) {
				return new Either([__('Error in update.', 'solid-affiliate')], $instance, false);
			} else {
				/** @var string $model */
				$model = static::MODEL_NAME;
				/**
				 * Fires after a model has been updated..
				 *
				 * @since TBD
				 *
				 * @param int                           $row_id   The updated model ID in the model table.
				 * @param MikesDataModelTrait|MikesDataModel $instance The previous instance of the model, before the update.
				 */
				do_action("solid_affiliate/{$model}/update", $new_instance->id, $instance);
				return new Either([''], $new_instance, true);
			}
		} else {
			/** @var \wpdb $wpdb */
			$error = $wpdb->last_error;
			return new Either([__($error, 'solid-affiliate')], $instance, false);
		}
	}

	/**
	 * Update many records.
	 *
	 * @param self[] $instances
	 * @param array $args
	 *
	 * @return array<Either<self>>
	 */
	public static function updateInstances($instances, $args = array())
	{
		// TODO N+1
		$responses = array_map(function ($i) use ($args) {
			return self::updateInstance($i, $args);
		}, $instances);

		return $responses;
	}


	/**
	 * Searches DB and returns item
	 * 
	 * @global \wpdb $wpdb
	 *
	 * @param array $args
	 * @param array<string> $search_args
	 * @param array<array<string>> $filter_args
	 * @param array<int> $where_ids
	 *
	 * @return array<object>
	 */
	public static function search($args = array(), $search_args = array(), $filter_args = array(), $where_ids = array())
	{
		global $wpdb;
		// custom search things
		if (empty($search_args)) {
			$search_sql = "";
		} else {
			$maybe_where = (empty($filter_args)) ? 'WHERE' : '';
			$search_sql = "{$maybe_where} {$search_args[0]} LIKE '%{$search_args[1]}%'";
		}

		// custom filter things
		if (empty($filter_args)) {
			if (!empty($where_ids)) {
				$where_string = 'id IN (' . implode(',', array_map('intval', $where_ids)) . ')';
				$filter_sql = "WHERE " . $where_string;
			} else {
				$filter_sql = "";
			}
		} else {
			$maybe_and = (empty($search_args)) ? '' : 'and';
			$prefix = "WHERE ";

			$where_strings = array_map(function ($tuple) {
				return "{$tuple[0]} = '{$tuple[1]}'";
			}, $filter_args);

			// deal with where_ids
			if (!empty($where_ids)) {
				$where_strings[] = 'id IN (' . implode(',', array_map('intval', $where_ids)) . ')';
			}

			$joined_where_string = implode(' and ', $where_strings);

			$filter_sql = $prefix . $joined_where_string .  "{$maybe_and}";
		}

		$defaults = array(
			'number'     => 20,
			'offset'     => 0,
			'orderby'    => 'id',
			'order'      => 'ASC',
		);

		$args      = wp_parse_args($args, $defaults);

		/**
		 * @psalm-suppress MixedOperand
		 * @psalm-suppress MixedPropertyFetch
		 **/
		$query = 'SELECT * FROM ' . $wpdb->prefix . self::TABLE . ' ' . $filter_sql . ' ' . $search_sql . ' ORDER BY ' . $args['orderby'] . ' ' . $args['order'] . ' LIMIT ' . $args['offset'] . ', ' . $args['number'];

		/**
		 * @var array<object>
		 * @psalm-suppress MixedMethodCall
		 **/
		$items = $wpdb->get_results($query);

		return $items;
	}

	/**
	 * Returns count.
	 *
	 * @param array<string,mixed> $args
	 *
	 * @return int
	 */
	public static function count($args = [])
	{
		// Pull specific data from args
		unset($args['limit']);
		unset($args['offset']);
		$keywords = isset($args['keywords']) ? sanitize_text_field((string)$args['keywords']) : '';
		unset($args['keywords']);
		// Build query and retrieve
		$builder = new QueryBuilder(self::TABLE . '_count');
		return $builder->from(self::TABLE . ' as `' . self::TABLE . '`')
			->keywords($keywords, static::$keywords)
			->where($args)
			->count();
	}

	/**
	 * Returns initialized builder with model set in from statement.
	 *
	 * @return QueryBuilder
	 */
	public static function builder()
	{
		$builder = new QueryBuilder(self::TABLE . '_custom');
		return $builder->from(self::TABLE . ' as `' . self::TABLE . '`');
	}

	/**
	 * Returns a collection with all models found in the database.
	 *
	 * @return array<self>
	 */
	public static function all()
	{
		// Build query and retrieve
		$builder = new QueryBuilder(self::TABLE . '_all');
		return array_map(
			/** 
			 * @param mixed[] $attributes
			 */
			function ($attributes) {
				return new self($attributes);
			},
			$builder->select('*')
				->from(self::TABLE . ' as `' . self::TABLE . '`')
				->get(ARRAY_A)
		);
	}

	/**
	 * Returns the last model found in the database.
	 *
	 * @return self|null
	 */
	public static function first()
	{
		// Build query and retrieve
		$builder = new QueryBuilder(self::TABLE . '_first');
		$attributes = $builder->select('*')
			->from(self::TABLE . ' as `' . self::TABLE . '`')
			->order_by('id', 'ASC')
			->limit(1)
			->get(ARRAY_A);

		// consider the case if no results are found
		if (empty($attributes)) {
			return null;
		} else {
			return new self($attributes[0]);
		}
	}

	/**
	 * Returns the last model found in the database.
	 *
	 * @return self|null
	 */
	public static function last()
	{
		// Build query and retrieve
		$builder = new QueryBuilder(self::TABLE . '_last');
		$attributes = $builder->select('*')
			->from(self::TABLE . ' as `' . self::TABLE . '`')
			->order_by('id', 'DESC')
			->limit(1)
			->get(ARRAY_A);

		// consider the case if no results are found
		if (empty($attributes)) {
			return null;
		} else {
			return new self($attributes[0]);
		}
	}

	/**
	 * Returns a collection of models.
	 * @since 1.0.0
	 *
	 * @param array $args
	 * @return self[]
	 */
	public static function where($args = [])
	{
		// Pull specific data from args
		$limit = isset($args['limit']) ? $args['limit'] : null;
		unset($args['limit']);
		$offset = isset($args['offset']) ? $args['offset'] : 0;
		unset($args['offset']);
		$keywords = isset($args['keywords']) ? $args['keywords'] : null;
		unset($args['keywords']);
		$keywords_separator = isset($args['keywords_separator']) ? $args['keywords_separator'] : ' ';
		unset($args['keywords_separator']);
		$order_by = isset($args['order_by']) ? $args['order_by'] : null;
		unset($args['order_by']);
		$order = isset($args['order']) ? $args['order'] : 'ASC';
		unset($args['order']);
		// Build query and retrieve
		$builder = new QueryBuilder(self::TABLE . '_where');
		return array_map(
			function ($attributes) {
				return new self($attributes);
			},
			$builder->select('*')
				->from(self::TABLE . ' as `' . self::TABLE . '`')
				->keywords($keywords, static::$keywords, $keywords_separator)
				->where($args)
				->order_by($order_by, $order)
				->limit($limit)
				->offset($offset)
				->get(ARRAY_A)
		);
	}

	/**
	 * Returns a collection of models, paginated.
	 * @since 1.0.0
	 *
	 * @param array{limit: int, page: int} $pagination_args
	 * @param array $where_args
	 * @return self[]
	 */
	public static function paginate($pagination_args, $where_args = [])
	{
		$limit = $pagination_args['limit'];
		$offset = ($pagination_args['page'] - 1) * $limit;

		$limit_offset_args = [
			'limit' => $limit,
			'offset' => $offset
		];

		$args = array_merge(
			$where_args,
			$limit_offset_args
		);

		return self::where($args);
	}

	/**
	 * Returns list of protected/readonly properties for
	 * when saving or updating.
	 * 
	 * NOTE: Mike overrides this so that 'created_at' and 'updated_at' will work on save and insert and such.
	 *       The "ORM" framework we're using (github 10quality) forces created_at and updated_at to be protected properties. idk why. idc.
	 * @since 1.0.0
	 * 
	 * @return array
	 */
	protected function protected_properties()
	{
		return [$this->primary_key];
	}

	/**
	 * Returns query results from mass update.
	 * @since 1.0.12
	 * 
	 * @param array $set   Set of column => data to update.
	 * @param array $where Where condition.
	 * 
	 * @return self|null
	 */
	public static function update_all($set, $where = [])
	{
		$builder = self::builder();
		return $builder->set(wp_unslash($set))
			->where($where)
			->update();
	}

	/**
	 * Returns an array of IDs of the records which match a condition.
	 * 
	 * @param array $where Where condition.
	 *
	 * @return array<int>
	 */
	public static function select_ids($where = [])
	{
		// Build query and retrieve
		$builder = new QueryBuilder(self::TABLE . '_select_ids');

		$res = $builder->select('id')
			->from(self::TABLE . ' as `' . self::TABLE . '`')
			->where($where)
			->get(ARRAY_N);

		$res = array_map(function ($tuple) {
			return (int)$tuple[0];
		}, $res);

		return $res;
	}
}
