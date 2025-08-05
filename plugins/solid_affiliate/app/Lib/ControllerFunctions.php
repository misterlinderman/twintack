<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;

class ControllerFunctions
{

    /**
     * Handles extracting, parsing, and validating POST parameters.
     *
     * The first argument is the $_POST request global.
     * The second argument indicates which keys you want extracted.
     * The third argument is a SCHEMA type array, indicating which fields
     * are required, their types, their defaults etc.
     *
     * Example:
     *
     * handle_arg_parsing_and_validation($_POST, ['user_id', 'commission_rate'], Affiliate::schema());
     *
     * => Either::Right<
     *       [
     *         'user_id' => 17,
     *         'commission_rate' => 22.50
     *       ]
     *    >
     * 
     * or
     * 
     * => Either::Left<
     *       ['Error: user_id is required']
     *    >
     * 
     * @template Tparam_key of string
     *
     * @param array $post
     * @param array<Tparam_key> $param_keys
     * @param Schema $schema
     *
     * @return Either<array<Tparam_key,mixed>>
     */
    public static function extract_and_validate_POST_params($post, $param_keys, $schema)
    {
        // TODO we should run all the SchemaEntry::validate and SchemaEntry::sanitize->

        $params = ControllerFunctions::_get_many_validated_params_from_POST($post, $param_keys, $schema);
        $errors = ControllerFunctions::_get_required_param_keys_missing_errors($params, $param_keys, $schema);

        if ($errors) {
            return new Either($errors, $params, false);
        } else {
            return new Either([''], $params, true);
        }
    }

    /**
     * Takes an array of params and filters out params that should not prefill a form after a failed submit.
     * The ScehmaEntry declares whether the value should be persisted.
     *
     * @template Tparam_key of string
     * @template Tsubmit_action of string
     *
     * @param array<Tparam_key, mixed> $params
     * @param Schema $schema
     * @param Tsubmit_action $submit_action
     *
     * @return array{submit_action: Tsubmit_action}&array<Tparam_key, mixed>
     */
    public static function params_to_persist_on_form_submit($params, $schema, $submit_action)
    {
        $keys = array_keys(SchemaFunctions::persist_value_on_form_submit_entries($schema));

        $schema_params = array_filter($params, function (string $key) use ($keys) {
            return in_array($key, $keys);
        }, ARRAY_FILTER_USE_KEY);

        return array_merge($schema_params, ['submit_action' => $submit_action]);
    }

    /**
     * Given the $_POST object and args, this function will attempt to delete models
     * from the databse and then redirect appropriately.
     * 
     * Assumptions this function currently makes:
     *   - The resource IDs are in $post['id']
     *
     * @param array $post
     * @param array{page: string, success_msg: string, error_msg: string, delete_by_id_function: Closure(int):Either<int>} $args
     * @return void
     */
    public static function generic_delete_handler($post, $args)
    {
        $page = isset($args['page']) ? $args['page'] : '';
        $success_msg = isset($args['success_msg']) ? $args['success_msg'] : '';
        $error_redirect_url = add_query_arg(['action' => 'delete'], URLs::admin_path($page, true));
        $success_redirect_url = URLs::admin_path($page, true);
        ////////////////////////////////////////////////////////////////////////////

        ControllerFunctions::enforce_current_user_capabilities(['read']);
        $ids = isset($post['id']) ? Validators::array_of_int($post['id']) : [0];

        if (empty($ids)) {
            ControllerFunctions::handle_redirecting_and_exit($error_redirect_url, [__('Error: ID is required', 'solid-affiliate')], [], 'admin', ['id' => $ids]);
        } else {
            //TODO N+1 but we want to keep it this way so that our data integrity callbacks happen.
            $delete_by_id_eithers = array_map(
                /** @param int $id */
                function ($id) use ($args) {
                    return $args['delete_by_id_function']($id);
                },
                $ids
            );

            $lefts = array_filter($delete_by_id_eithers, function ($either) {
                return $either->isLeft;
            });

            $error_messages = array_map(function ($either) {
                return $either->left[0];
            }, $lefts);

            $rights = array_filter($delete_by_id_eithers, function ($either) {
                return $either->isRight;
            });

            if (empty($rights)) {
                ControllerFunctions::handle_redirecting_and_exit($error_redirect_url, $error_messages, [], 'admin');
            } else {
                $deleted_ids = array_map(function ($either) {
                    return $either->right;
                }, $rights);
                $success_msg = $success_msg . ' ' . implode(', ', $deleted_ids);

                ControllerFunctions::handle_redirecting_and_exit($success_redirect_url, $error_messages, [$success_msg], 'admin');
            }
        }
    }

    /**
     * Given the $_POST object and args, this function will attempt to Upsert models
     * into the databse and then redirect appropriately.
     * 
     * Assumptions this function currently makes:
     *   - The resource ID is in $post['field_id'] if it's an Update. If that key is missing, it's an insert.
     *
     * TODO: the second param feels like it should be its own type interface?
     *
     * @param array $post
     * @param array{page: string, class_string: class-string<MikesDataModel>, success_msg_create: string, success_msg_update:string, error_msg: string, schema: Schema, upsert_function: Closure(array<string, mixed>):(Either<int>), capability: string} $args
     * @return void
     */
    public static function generic_upsert_handler($post, $args)
    {
        if (!current_user_can($args['capability'])) {
            // TODO add Affiliate management specific caps to check here.
            wp_die(__('Permission Denied!', 'solid-affiliate'));
        }

        $field_id = isset($post['field_id']) ? (int) $post['field_id'] : 0;
        $creating_new_resource = !$field_id ? true : false;

        $expected_form_inputs = $creating_new_resource ?
            SchemaFunctions::keys_on_new_form_from_schema($args['schema']) :
            SchemaFunctions::keys_on_edit_form_from_schema($args['schema']);

        // Generic validation.
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $post,
            $expected_form_inputs,
            $args['schema']
        );


        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left, [], 'admin');
        } else {
            if (!$creating_new_resource) {
                // We're updating so set the id so the upsert_function knows its an update... :/
                $eitherFields->right['id'] = $field_id;
            }
            $eitherUpsert = $args['upsert_function']($eitherFields->right);
            if ($eitherUpsert->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherUpsert->left, [], 'admin');
            } else {
                $redirect_url = $creating_new_resource ? URLs::index($args['class_string'], true) : URLs::edit($args['class_string'], $field_id, true);
                $success_msg = $creating_new_resource ? $args['success_msg_create'] : $args['success_msg_update'];
                ControllerFunctions::handle_redirecting_and_exit($redirect_url, [], [$success_msg], 'admin');
            }
        }
    }

    /**
     * Will call wp_die if any of the capabilities fail and there is no alternative reason the current user should have permission.
     * If the capability of the current is based off something other than the native WordPress capabilities, then pass that function in as $alt_cap_func.
     *
     * @param array<string> $capabilities
     * @param null|callable():bool $alt_cap_func
     *
     * @return void
     */
    public static function enforce_current_user_capabilities($capabilities, $alt_cap_func = null)
    {
        foreach ($capabilities as $capability) {
            if (!current_user_can($capability)) {
                if (is_null($alt_cap_func)) {
                    wp_die(__('Permission Denied!', 'solid-affiliate'));
                    return;
                }

                if (!call_user_func($alt_cap_func)) {
                    wp_die(__('Permission Denied!', 'solid-affiliate'));
                }
            }
        }
    }

    /**
     * Handles adding errors to a redirect_url and calling wp_safe_redirect
     *
     * Use the magic string 'REDIRECT_BACK' as the first parameter, and the function will
     * internally use $_POST['_wp_http_referer'] as the redirect path.
     * 
     * @param string|'REDIRECT_BACK' $redirect_path
     * @param array<string> $errors
     * @param array<string> $success_messages
     * @param 'home'|'admin' $home_or_admin
     * @param array $args_to_add
     * @param boolean $replace_params
     *
     * @psalm-suppress InvalidReturnType
     * @return void
     */
    public static function handle_redirecting_and_exit($redirect_path, $errors = [], $success_messages = [], $home_or_admin = 'home', $args_to_add = [], $replace_params = false)
    {
        # TODO: Why are errors are not passed back as $args_to_add like the rest of the query params?
        #       Is it because they are arrays and the first of each type of message is taken out of the array to be added?

        if ($redirect_path === 'REDIRECT_BACK') {
            $redirect_path = wp_get_raw_referer();
            if (!$redirect_path) {
                $redirect_path = '/';
            }

            # NOTE: See AffiliatePortalController::POST_affiliate_login_handler comment.
            #       This replaces the the REDIRECT_BACK URL with the explicit $args_to_add, instead of
            #       adding them to the previous URL creating a combination of query params.
            #       Is this how we want to handle params when more than one form exists on a page?
            if ($replace_params) {
                $redirect_path = parse_url($redirect_path, PHP_URL_PATH);
            }

            self::_redirect_to_url($redirect_path, $errors, $success_messages, $args_to_add);
        } else {
            $redirect_path = str_replace(home_url(), '', $redirect_path);
            $redirect_url = ($home_or_admin === 'home') ? home_url($redirect_path) : admin_url($redirect_path);

            self::_redirect_to_url($redirect_url, $errors, $success_messages, $args_to_add);
        }
    }

    /**
     * Redirects to a url and exits.
     *
     * @param string|'REDIRECT_BACK' $redirect_url
     * @param array<string> $errors
     * @param array<string> $success_messages
     * @param array $args_to_add
     *
     * @return never
     */
    public static function _redirect_to_url($redirect_url, $errors = [], $success_messages = [], $args_to_add = [])
    {
        $error_query_param = Notices::URL_PARAM_ERROR;

        if ($errors) {
            $first_error = urlencode(reset($errors));
            $redirect_url = add_query_arg(array($error_query_param => $first_error), $redirect_url);
        } else {
            $redirect_url = remove_query_arg($error_query_param, $redirect_url);
        }

        if ($success_messages) {
            $first_message = urlencode(reset($success_messages));
            $redirect_url = add_query_arg(array(Notices::URL_PARAM_MESSAGE => $first_message), $redirect_url);
        } else {
            $redirect_url = remove_query_arg(Notices::URL_PARAM_MESSAGE, $redirect_url);
        }

        # TOOD:2: Figure out why rawurlencode seems to be behaving differently in tests vs local
        $args_to_add = Validators::arr($args_to_add);
        $encoded_args = array_reduce(
            array_keys($args_to_add),
            /**
             * @param array $arr
             * @param string|int $key
             */
            function ($arr, $key) {
                /** @var mixed $val */
                $val = $arr[$key];
                if (is_string($val) && Utils::should_be_url_encoded($val)) {
                    $val = urlencode($val);
                }
                if (is_array($val)) {
                    $val = array_map(
                        function ($item) {
                            if (is_string($item) && Utils::should_be_url_encoded($item)) {
                                return urlencode($item);
                            } else {
                                return $item;
                            }
                        },
                        $val
                    );
                }
                # TODO:1: why can't I set the val here as a mixed type
                /** @var string|array|int|boolean|float $val */
                $arr[$key] = $val;
                return $arr;
            },
            $args_to_add
        );

        $redirect_url = add_query_arg($encoded_args, $redirect_url);
        $redirect_url = wp_validate_redirect($redirect_url);

        if (strlen($redirect_url) > 2000) {
            $redirect_url = substr($redirect_url, 0, 2000);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Takes a GET request and extracts an array of ids given the id[] query paramater(s)
     *
     * @param array $get
     * @return array<int>
     */
    public static function extract_ids_from_get($get)
    {
        $ids = isset($get["id"]) ? (array)$get["id"] : [0];
        $ids = array_map(function ($id) {
            return intval($id);
        }, $ids);

        return $ids;
    }

    /**
     * 
     * _get_many_validated_params_from_POST($_POST, ['user_id', 'commission_rate'], Affiliate::schema());
     *  =>   [
     *         'user_id' => 17,
     *         'commission_rate' => 22.50
     *       ]
     * 
     * @template Tparam_key of string
     *
     * @param array $post
     * @param array<Tparam_key> $param_keys
     * @param Schema $schema
     *
     * @return array<Tparam_key, mixed>
     */
    private static function _get_many_validated_params_from_POST($post, $param_keys, $schema)
    {
        $validated_post_values = array_map(
            /** @param Tparam_key $key */
            function ($key) use ($post, $schema) {
                $val = isset($post[$key]) ? SchemaFunctions::sanitize_val_based_on_schema_and_key($schema, $key, $post[$key]) : SchemaFunctions::default_for_empty_schema_key($schema, $key);
                return $val;
            },
            $param_keys
        );

        /** @var array<Tparam_key,mixed> $fields */
        $fields = array_combine($param_keys, $validated_post_values);

        return $fields;
    }

    /**
     * @template Tparam_key of string
     *
     * @param array<Tparam_key, mixed> $params
     * @param array<Tparam_key> $param_keys_to_check
     * @param Schema $schema
     *
     * @return array<string>
     */
    private static function _get_required_param_keys_missing_errors($params, $param_keys_to_check, $schema)
    {
        $required_fields = SchemaFunctions::required_fields_from_schema($schema);
        $errors = array_reduce(
            $param_keys_to_check,
            /**
             * @param string[] $errors
             * @param string $param_key
             *
             * @return string[]
             **/
            function ($errors, $param_key) use ($params, $required_fields, $schema) {
                // allow 0 value for when a required referral rate can be 0.

                # NOTE: This should catch all float or bigint values
                if (isset($params[$param_key]) && Utils::is_zero_number_and_not_considered_empty($schema, $params, $param_key)) {
                    return $errors;
                }

                if (!isset($params[$param_key]) || Utils::is_empty($params[$param_key])) {
                    if (in_array($param_key, $required_fields)) {
                        $error = SchemaFunctions::_error_msg_for_required_schema_key($schema, $param_key);
                        return array_merge($errors, [$error]);
                    }
                    return $errors;
                } else {
                    return $errors;
                }
            },
            []
        );

        return $errors;
    }
}
