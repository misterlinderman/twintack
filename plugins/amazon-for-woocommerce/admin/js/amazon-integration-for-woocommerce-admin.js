(function ($) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	var ajaxUrl   = ced_amazon_admin_obj.ajax_url;
	var ajaxNonce = ced_amazon_admin_obj.ajax_nonce;
	var user_id   = ced_amazon_admin_obj.user_id;

	const queryString = window.location.search;
	var urlParams     = new URLSearchParams(queryString);

	document.addEventListener(
		"readystatechange",
		(event) => {
			if (event.target.readyState === "interactive") {
				ced_display_loader()
			} else if (event.target.readyState === "complete") {
				setTimeout(
					() => {
						ced_hide_loader();
					},
					500
				)
			}
		}
	);

	function remove_custom_notice(reload = 'no') {

		if ($('#ced_amazon_custom_notice').hasClass('ced_amazon_notice')) {
			setTimeout(
				() => {
					$('.ced_amazon_notice').remove();
					if (reload == 'yes') {
						window.location.reload();
					}
				},
				3000
			)
		}
	}

	async function ced_amazon_fetch_next_level_category( category_data, template_id, display_saved_values) {
		
		// ced_display_loader();
		
		let user_id   = urlParams.get('user_id');
		let seller_id = urlParams.get('seller_id');

		let categoryResponse;

		await $.ajax(
			{
				url: ajaxUrl,
				data: {
					ajax_nonce: ajaxNonce,
					action: 'ced_amazon_fetch_next_level_category',
					category_data: category_data,
					template_id: template_id,
					user_id: user_id,
					seller_id: seller_id,
					display_saved_values: display_saved_values
				},
				type: 'POST',
				success: function (response) {

					if (response.success == false) {
						if ($('.notice-error').length > 0) {

							$('.notice-error').html('<p>' + response.data.message + '</p>');
							$('#ced_amz_tmp_radio_btn').prop("checked", false);

						} else {
							$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
							$('#ced_amz_tmp_radio_btn').prop("checked", false);
						}
					}
					categoryResponse = response;
				}
			}
		);

		return categoryResponse;
	}

	$(document).on(
		'click',
		'.ced_amazon_add_account_button',
		function (e) {

			e.preventDefault();

			var marketplaceId = jQuery("#ced_amazon_select_marketplace_region").find("option:selected").attr("mp-id");
			let reAuthorising = jQuery(this).attr("re-authorising");
			reAuthorising     = Boolean(reAuthorising);

			if (reAuthorising) {
				marketplaceId = jQuery(this).attr("mp-id");
			}

			if (marketplaceId == '' || marketplaceId == undefined || marketplaceId == null) {
				customSwal(
					{
						title: 'Error',
						text: 'Please select valid marketplace region.',
						icon: 'error',
					},
					() => { }
				)
				return;
			}

			ced_display_loader();

			jQuery.ajax(
				{
					type: 'POST',
					url: ajaxUrl,
					data: {
						ajax_nonce: ajaxNonce,
						marketplace_id: marketplaceId,
						reAuthorising: reAuthorising ? true : false,
						action: 'ced_amazon_create_sellernext_user',
					},
					success: function (response) {

						if (response.success) {
							location.href = response.data;
						}

					}
				}
			)

		}
	);

	document.addEventListener(
		"readystatechange",
		(event) => {
			if (event.target.readyState === "complete") {

				let searchParams   = new URLSearchParams(window.location.href);
				let paramsIterator = searchParams.entries();
				let queryParams    = {};

				for (var pair of paramsIterator) {
					queryParams[pair[0]] = pair[1];
				}

				if ((queryParams['section'] == 'orders-view' || queryParams['section'] == 'products-view' || queryParams['section'] == 'feeds-view') && queryParams['channel'] == 'amazon') {

					let pp           = queryParams['per_page'] ? queryParams['per_page'] : '20';
					let perpage_html = '';

					perpage_html         = [10, 20, 30, 50].map(
						(e) => {
							let selected = '';
							selected     = e == pp ? 'selected' : '';
							return '<option ' + selected + ' value = "' + e + '" >' + e + ' per page</option>'
						}
					)

					queryParams['section'] == 'orders-view' ? $('.tablenav').prepend('<select id="ced_amz_order_per_page" >' + perpage_html + '</select>') : '';
					queryParams['section'] == 'products-view' ? $('.tablenav').prepend('<select id="ced_amz_product_per_page" >' + perpage_html + '</select>') : '';
					queryParams['section'] == 'feeds-view' ? $('.tablenav').prepend('<select id="ced_amz_feed_per_page" >' + perpage_html + '</select>') : '';

				}

			}
		}
	);

	function pageRedirectFunction(tag) {

		ced_display_loader();

		let val        = $(tag).val();
		let currentURL = window.location.href;
		let newParams  = "&per_page=" + val;

		let modifiedURL = removeURLParameter(currentURL, 'paged');
		let newURL      = modifiedURL + newParams;
		location.href   = newURL;
	}

	jQuery(document).on(
		'change',
		'#ced_amz_order_per_page, #ced_amz_product_per_page, #ced_amz_feed_per_page',
		function (e) {
			if ($(e.target).attr('id') == 'ced_amz_order_per_page') {
				pageRedirectFunction('#ced_amz_order_per_page');
			} else if ($(e.target).attr('id') == 'ced_amz_product_per_page') {
				pageRedirectFunction('#ced_amz_product_per_page');
			} else if ($(e.target).attr('id') == 'ced_amz_feed_per_page') {
				pageRedirectFunction('#ced_amz_feed_per_page');
			}

		}
	)

	function notNullAndEmpty(variable) {

		if (variable == null || variable == "" || variable == 0 || variable == 'null') {
			return false;
		}

		return true;
	}

	$(document).on(
		'click',
		'.ced_amazon_fetch_orders, .ced_amz_import_order_manually',
		function (event) {

			event.preventDefault();
			ced_display_loader();

			let order_id = $('.ced_amz_order_ID').val();

			if ( !notNullAndEmpty(order_id) ) {
				order_id = $(this).attr('id');
			}

			$.ajax({
				url: ajaxUrl,
				data: {
					ajax_nonce: ajaxNonce,
					action: 'ced_amazon_get_orders',
					amz_order_id: order_id,
					seller_id: urlParams.get('seller_id'),
						
				},
				type: 'POST',
				success: function (response) {
					ced_hide_loader();

					if ( response.success ) {
						customSwal(
							{
								title: 'Success',
								text: response.data.message,
								icon: response.data.status,
							},() => { })
					} else {
						customSwal({
							title: 'Error',
							text: response.data.message,
							icon: response.data.status,
							},() => { }
						)

					}

				}
				}
			);
		}

	);

	$(document).on(
		'click',
		'#ced_amazon_continue_wizard_button',
		function (e) {

			let currentStep = $(this).data('attr');
			let user_id     = urlParams.get('user_id');

			jQuery.ajax({
				type: 'POST',
				url: ajaxUrl,
				data: {
					ajax_nonce: ajaxNonce,
					action: 'ced_amazon_update_current_step',
					current_step: currentStep,
					user_id: user_id
				},
				success: function (response) {
				}
				}
			)

		}
	)

	jQuery(document).on(
		'click',
		'#ced_amazon_disconnect_account_btn',
		function (e) {

			let sellernextShopId = $(this).attr('sellernext-shop-id');
			let seller_id        = $(this).attr('seller-id');
			e.preventDefault();

			$('#ced_amazon_verf_disconnect_account_btn').attr('sellernext-shop-id', sellernextShopId);
			$('#ced_amazon_verf_disconnect_account_btn').attr('seller_id', seller_id);

			$('#ced-amazon-disconnect-account-modal').show();

		}
	);

	jQuery(document).on(
		'click',
		'#ced_amazon_verf_disconnect_account_btn',
		function (e) {

			let sellernextShopId = $(this).attr('sellernext-shop-id');
			let seller_id        = $(this).attr('seller_id');
			e.preventDefault();

			$('#ced-amazon-disconnect-account-modal').hide();
			ced_display_loader();

			jQuery.ajax({
				type: 'post',
				url: ajaxUrl,
				data: {
					seller_id: seller_id,
					sellernextShopId: sellernextShopId,
					ajax_nonce: ajaxNonce,
					action: 'ced_amazon_remove_account_from_integration'
				},
				success: function (response) {

					$('#wpbody-content').unblock();
					var html = '<div class="notice notice-success"><p>' + response.message + '</p></div>';
					$('.ced_amazon_error').html(html);

					setTimeout(() => {
						window.location.reload()
						}, 2000)

				}
				}
			)

		}
	);

	$(document).on(
		'click',
		'.ced-close-button',
		function () {
			$('#ced-amazon-disconnect-account-modal').hide();
		}
	)

	jQuery(document).on(
		"click",
		".ced_amazon_add_rows_button",
		function (e) {

			e.preventDefault();
			let custom_field     = $(this).parents('tr').children('td').eq(1).find('select');
			let custom_field_val = custom_field.val();
			let id               = $(this).attr('id');
			let fileUrl          = $('.ced_amazon_profile_url').val();

			id = escapeBrackets(id);

			let primary_cat   = $('.ced_primary_category').val();
			let secondary_cat = $('.ced_secondary_category').val();

			let file_url = '';

			if (notNullAndEmpty(primary_cat) && notNullAndEmpty(secondary_cat)) {

				if ('' == custom_field_val) {
					window.scrollTo(0, 0);
					if ($('.notice-error').length > 0) {
						$('.notice-error').html('<p>Unable to add new optional rows.</p>')
					} else {
						$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>Please select a optional row. </p></div>')

					}
					return;
				}

				ced_display_loader()

				jQuery.ajax(
					{
						type: 'post',
						url: ajaxUrl,
						data: {
							userid: user_id,
							ajax_nonce: ajaxNonce,
							custom_field: JSON.parse(custom_field_val),
							primary_cat: primary_cat,
							secondary_cat: secondary_cat,
							template_type: $('.ced_amazon_template_type').val(),
							fileUrl: fileUrl,
							dataType: "html",
							action: 'ced_amazon_add_custom_profile_rows'
						},
						success: function (response) {

							response = JSON.parse(response);

							$(".ced-faq-content-wrap").animate({ scrollTop: $(".ced-faq-content-wrap")[0].scrollHeight }, 1000);

							ced_hide_loader();
							$('#' + id).parents('tr').before(response.data);
							$('#optionalFields option:selected').remove();
							custom_field.val('');
							$("#optionalFields").val(null).trigger("change");
							$('.custom_category_attributes_select').selectWoo(
								{
									dropdownPosition: 'below',
									dropdownAutoWidth: true,
									allowClear: true,
									placeholder: '--Select--',
									width: 'resolve'
								}
							);
							$('.custom_category_attributes_select2').selectWoo(
								{
									dropdownPosition: 'below',
									allowClear: true,
									placeholder: '--Select--',
									width: '90%'
								}
							);

							jQuery('.woocommerce-help-tip').tipTip(
								{
									attribute: 'data-tip',
									content: jQuery('.woocommerce-help-tip').attr('data-tip'),
									fadeIn: 50,
									fadeOut: 50,
									delay: 200,
									keepAlive: true,
								}
							);

							$('.woocommerce-importer').find('.woocommerce-help-tip').css({ position: 'absolute', right: 0, top: '47%' })

						}
					}
				)

			} else {
				window.scrollTo(0, 0);
				if ($('.notice-error').length > 0) {
					$('.notice-error').html('<p>Unable to add new optional rows.</p>')
				} else {
					$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>Unable to add new optional rows</p></div>')
					return;
				}

			}
		}
	);

	jQuery(document).on(
		"click",
		"#update_template",
		function (e) {

			e.preventDefault();

			let primary_cat   = $('.ced_primary_category').val();
			let secondary_cat = $('.ced_secondary_category').val();
			let browse_nodes  = $('.ced_product_type').val();

			if (notNullAndEmpty(primary_cat) && notNullAndEmpty(secondary_cat)) {

				let user_id   = urlParams.get('user_id');
				let seller_id = urlParams.get('seller_id');

				ced_display_loader()

				jQuery.ajax(
					{
						type: 'post',
						url: ajaxUrl,
						data: {
							ajax_nonce: ajaxNonce,
							primary_cat: primary_cat,
							secondary_cat: secondary_cat,
							browse_nodes: browse_nodes,
							user_id: user_id,
							seller_id: seller_id,
							action: 'ced_amazon_update_template'
						},
						success: function (response) {
							if (typeof response == 'string') {
								response = JSON.parse(response);
							} else if (typeof response == 'object') {
								response = response;
							}

							ced_hide_loader();

							customSwal(
								{
									title: 'Update',
									text: response.message,
									icon: response.status,
								},
								() => { window.location.reload(); }
							)

						},
						error: function (error) {
							window.scrollTo(0, 0);
							if ($('.notice-error').length > 0) {
								$('.notice-error').html('<p>Unable to update template fields. Please try again later.</p>')
							} else {
								$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>Unable to update template fields. Please try again later.</p></div>')
								return;
							}
						}
					}
				)

			} else {
				window.scrollTo(0, 0);
				if ($('.notice-error').length > 0) {
					$('.notice-error').html('<p>Unable to update template fields. Please try again later.</p>')
				} else {
					$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>Unable to update template fields. Please try again later.</p></div>')
					return;
				}

			}

		}
	);

	function checkSellerNextCategoryApi() {

		let template_id = urlParams.get('template_id');

		if (template_id !== '' && template_id !== null) {
			CategoryApiLoop(template_id);
		}

	}

	async function CategoryApiLoop(template_id) {

		let i            = 3;
		let product_type = $('.ced_product_type').val();

		let category_data = {
			product_type: product_type,
			product_type_name: product_type.replaceAll( '_', ' ' )
		};

		ced_display_loader()
		let categoryResponse = await ced_amazon_fetch_next_level_category( category_data, template_id, 'yes');
		// ced_hide_loader();

		await handleCategoryResponse(categoryResponse)

	}

	function handleCategoryResponse(response) {

		if (typeof response == 'string') {
			response = JSON.parse(response);
		}

		if (response.success) {

			$('.ced_template_required_attributes').html(response.data);
			$('.custom_category_attributes_select').selectWoo(
				{
					dropdownPosition: 'below',
					allowClear: true,
					placeholder: '--Select--',
					width: 'resolve'
				}
			);

			$('.custom_category_attributes_select2').selectWoo(
				{
					dropdownPosition: 'below',
					allowClear: true,
					placeholder: '--Select--',
					width: '90%'
				}
			);

			$('#optionalFields').selectWoo(
				{
					dropdownPosition: 'below',
					allowClear: true,
					placeholder: '--Select--',
					width: '90%'
				}
			);
			jQuery('.woocommerce-help-tip').tipTip(
				{
					attribute: 'data-tip',
					content: jQuery('.woocommerce-help-tip').attr('data-tip'),
					fadeIn: 50,
					fadeOut: 50,
					delay: 200,
					keepAlive: true,
				}
			);

			$('.woocommerce-importer').find('.woocommerce-help-tip').css({ position: 'absolute', right: 0, top: '47%' })

			ced_hide_loader();

		} else {

			if ($('.notice-error').length > 0) {

				let message = response.hasOwnProperty("data") && response.data.hasOwnProperty("message") ? response.data.message : '';

				if (message.length == 0) {
					message = response.message;
				}

				$('.notice-error').html('<p>' + message + '</p>');
				$('.notice-error').addClass('ced_extended_notice');
				$('#ced_amz_tmp_radio_btn').prop("checked", false);

			} else {

				let message = response.hasOwnProperty("data") && response.data.hasOwnProperty("message") ? response.data.message : '';

				if (message.length == 0) {
					message = response.message;
				}

				$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
				$('#ced_amz_tmp_radio_btn').prop("checked", false);
			}

			ced_hide_loader();
		}

	}

	jQuery(document).on(
		'click',
		'#amazon_seller_verification',
		function (e) {

			e.preventDefault();
			ced_display_loader()

			jQuery.ajax(
				{
					type: 'post',
					url: ajaxUrl,
					data: {
						ajax_nonce: ajaxNonce,
						user_id: $(this).attr('dta-amz-shop-id'),
						seller_id: urlParams.get('seller_id'),
						mode: urlParams.get('mode'),
						action: 'ced_amazon_seller_verification'
					},
					success: function (response) {

						ced_hide_loader();

						if (response.success) {

							let url = window.location.href;

							url = removeParam("data", url);
							url = removeParam('app_code', url);
							url = removeParam('success', url);
							url = removeParam('marketplace', url);
							url = removeParam('state', url);

							window.location.replace(url + '&part=wizard-options&user_id=' + response.data.user_id + '&seller_id=' + response.data.seller_id)

						} else {
							customSwal(
								{
									title: 'Seller Verification Failed',
									text: response.message ? response.message : response.error,
									icon: 'error',
								},
								() => { return; }
							)
						}

					}
				}
			);

		}
	);

	function removeParam(key, sourceURL) {
		var rtn         = sourceURL.split("?")[0],
			param,
			params_arr  = [],
			queryString = (sourceURL.indexOf("?") !== -1) ? sourceURL.split("?")[1] : "";
		if (queryString !== "") {
			params_arr = queryString.split("&");
			for (var i = params_arr.length - 1; i >= 0; i -= 1) {
				param = params_arr[i].split("=")[0];
				if (param === key) {
					params_arr.splice(i, 1);
				}
			}
			if (params_arr.length) {
				rtn = rtn + "?" + params_arr.join("&");
			}
		}
		return rtn;
	}

	$(document).on(
		'click',
		'.add-new-template-btn',
		function (e) {

			let woo_used_categories = $(this).attr('data-woo-used-cat');
			let woo_all_categories  = $(this).attr('data-woo-all-cat');

			woo_used_categories = JSON.parse(woo_used_categories);
			woo_all_categories  = JSON.parse(woo_all_categories);

			if (woo_all_categories.sort().join(',') === woo_used_categories.sort().join(',')) {

				customSwal(
					{
						title: 'WooCommerce Category',
						text: 'All existing WooCommerce categories are already mapped. Please create a new WooCommerce category or remove some WooCommerce categories from the existing mapped profiles.',
						icon: 'error',
					},
					() => { return; }
				)
			} else {
				if ('URLSearchParams' in window) {
					var searchParams = new URLSearchParams(queryString);
					searchParams.set("section", "add-new-template");
					window.location.search = searchParams.toString();

				}
			}
		}
	)

	$(document).on(
		'click',
		'#ced_amazon_reset_product_page',
		function (e) {

			e.preventDefault();
			var searchParams = new URLSearchParams(queryString);
			searchParams.delete('searchType');
			searchParams.delete('searchQuery');
			searchParams.delete('searchCriteria');

			window.location.search = searchParams.toString();

		}
	)

	$(document).on(
		'click',
		'.ced_amazon_add_missing_fields',
		function (e) {

			e.preventDefault();
			e.stopPropagation();

			let title = $('.ced_amazon_add_missing_field_title').val();
			let slug  = $('.ced_amazon_add_missing_field_slug').val();
			title     = title.trim();

			let existing_custom_item_aspects_json;

			let existing_custom_item_aspects_string = $('.ced_amazon_add_missing_fields_heading').attr('data-attr');

			existing_custom_item_aspects_string = existing_custom_item_aspects_string.replaceAll("+", " ");
			if (existing_custom_item_aspects_string !== '') {

				existing_custom_item_aspects_json = JSON.parse(existing_custom_item_aspects_string);

				if (existing_custom_item_aspects_json.hasOwnProperty(slug) || Object.values(existing_custom_item_aspects_json).indexOf(title) > -1) {
					let html = '<tr class="ced_amazon_add_missing_field_error" ><td colspan="3">Please enter another custom title or slug. Same custom title or slug has already been used.</td></tr>'
					$('.ced_amazon_add_missing_field_row').before(html);

					setTimeout(
						() => {
							$('.ced_amazon_add_missing_field_error').remove();
						},
						3000
					)
					return;
				}

			}

			if (title.length <= 0 || slug.length <= 0) {
				let html = '<tr class="ced_amazon_add_missing_field_error" ><td colspan="3">Please enter additional field title and slug.</td></tr>'
				$('.ced_amazon_add_missing_field_row').before(html);

				setTimeout(
					() => {
						$('.ced_amazon_add_missing_field_error').remove();
					},
					3000
				)

			} else {

				if (existing_custom_item_aspects_string == '') {
					existing_custom_item_aspects_json       = {};
					existing_custom_item_aspects_json[slug] = title;
				} else {
					existing_custom_item_aspects_json       = JSON.parse(existing_custom_item_aspects_string);
					existing_custom_item_aspects_json[slug] = title;
				}

				$('.ced_amazon_add_missing_fields_heading').attr('data-attr', JSON.stringify(existing_custom_item_aspects_json));

				let primary_cat   = $('#ced_amazon_primary_category_selection').val();
				let secondary_cat = $('#ced_amazon_secondary_category_selection').val();

				ced_display_loader();

				jQuery.ajax(
					{
						type: 'post',
						url: ajaxUrl,
						dataType: 'html',
						data: {
							user_id: user_id,
							ajax_nonce: ajaxNonce,
							title: title,
							slug: slug,
							primary_cat: primary_cat,
							secondary_cat: secondary_cat,
							action: 'ced_amazon_add_missing_field_row'
						},
						success: function (response) {

							response = JSON.parse(response);
							jQuery('#wpbody-content .ced_amazon_overlay').remove();
							$('.ced_amazon_add_missing_fields_heading').after(response.data);
							$('.ced_amazon_add_missing_field_title').val('');
							$('.ced_amazon_add_missing_field_slug').val('');
							$('.custom_category_attributes_select').selectWoo();
							remove_custom_notice();

						}
					}
				)

			}

		}
	)

	$(document).on(
		'click',
		'.ced_amazon_remove_custom_row',
		function (e) {
			e.preventDefault();
			$(this).parents('tr').remove();
		}
	)

	$(document).on(
		'change',
		'.ced_amazon_change_acc',
		function (e) {
			ced_display_loader();

			let href       = $('select[name="ced_amazon_change_acc"] :selected').attr('data-href');
			let hrefParams = new URLSearchParams(href);

			// if (notNullAndEmpty(hrefParams.get('user_id')) && notNullAndEmpty(hrefParams.get('seller_id'))) {
			if (notNullAndEmpty(hrefParams.get('user_id')) || notNullAndEmpty(hrefParams.get('seller_id'))) {

				jQuery.ajax(
					{
						type: 'post',
						url: ajaxUrl,
						data: {
							ajax_nonce: ajaxNonce,
							user_id: hrefParams.get('user_id'),
							seller_id: hrefParams.get('seller_id'),
							action: 'ced_amazon_change_region'
						},
						success: function (response) {

							response = JSON.parse(response)

							jQuery('#wpbody-content .ced_amazon_overlay').remove();
							window.location.href = href;

						}
					}
				);

			} else {
				window.location.href = href;
			}

		}
	)

	$(document).ready(
		function () {

			jQuery('.woocommerce-help-tip').tipTip(
				{
					attribute: 'data-tip',
					content: jQuery('.woocommerce-help-tip').attr('data-tip'),
					fadeIn: 50,
					fadeOut: 50,
					delay: 200,
					keepAlive: true,
				}
			);

			let page    = urlParams.get('page');
			let section = urlParams.get('section') ? urlParams.get('section') : '';
			let part    = urlParams.get('part') ? urlParams.get('part') : '';

			if (section == 'orders-view') {
				$('#search_id-search-input').attr('placeholder', 'Enter Amazon Order ID');
			}

			if (section == 'add-new-template') {
				checkSellerNextCategoryApi();
			}

			if ( section == "schema-view" ) {
				// Get the raw JSON string (escaped safely for JS)
				// const rawJson = <?php echo json_encode($json); ?>;

				let rawJson = $('.ced_final_pro_details').data('schm');
				
				// Render the parsed JSON into HTML and display it
				document.getElementById("json-display").innerHTML = renderValue(rawJson);

			}

			if (page == 'ced_amazon' && section !== '' && section !== 'setup-amazon') {
				jQuery("#wpbody-content").addClass("ced-amz-not-setup");
			}


			if ( section == 'settings' || part == "wizard-options" ) {
				
				var nearestTable = $('#ced_amazon_rsrve_stck').closest('table');
				console.log(nearestTable); // You can perform any action on nearestTable here
				
				if ( part == "wizard-options" ) {
				  nearestTable.after('<p style="margin: 5%;  font-size: 13px;" ><i><b>Note:</b> The values set in the Global Options fields will be applied to all connected Amazon accounts.</i></p>');
				} else {
				  nearestTable.after('<p style="margin: 35px 0px; margin-left: 30%; width: 70%;" ><i><b>Note:</b> The values set in the Global Options fields will be applied to all connected Amazon accounts.</i></p>');
				}
				
			}

		}
	)


	/**
	 * Recursively renders JSON values into HTML
	 * Handles objects, arrays, and primitive types
	 */
	function renderValue(value) {
					
		if (typeof value === "object" && value !== null) {
			
			const isArray = Array.isArray(value);
			const entries = Object.entries(value);

			// Start container for object/array
			let html = `<div class="ced-json-container">`;
			
			// Add toggle control for collapse/expand
			html += `<span class="ced-json-toggle" >[–]</span>`;
			
			// Opening bracket for object or array
			html += isArray ? '[' : '{';
			html += `<div class="ced-json-content">`;

			// Loop through each key-value pair
			for (const [key, val] of entries) {
				html += `<div>`;
				if (!isArray) {
					// Render key name for objects
					html += `<span class="ced-json-key">"${key}"</span>: `;
				}
				// Recursively render the value
				html += renderValue(val);
				html += `</div>`;
			}

			// Close brackets
			html += `</div>${isArray ? ']' : '}'}</div>`;
			return html;
			
		} else {
			// Primitive value (string, number, boolean, null)
			return `<span class ="ced-json-value">${JSON.stringify(value)}</span>`;
		}
	}

	jQuery(document).on(
		'click',
	   '.ced-json-toggle',
		function () {
			const $container = $(this).parent();
			$container.toggleClass('collapsed');
			$(this).text($container.hasClass('collapsed') ? '[+]' : '[–]');
		});


		
	jQuery(document).on(
		'click',
		'.ced_heading',
		function () {
			
			let rawJson = jQuery(this).find('span').data('schm');
			
			jQuery('.ced_heading').removeClass('bckg');
			jQuery(this).addClass('bckg');
			document.getElementById("json-display").innerHTML = renderValue(rawJson);
			
		});



	// View feed response in feed table via ajax using modal
	jQuery(document).on(
		'click',
		'.feed-response',
		function (e) {

			ced_display_loader()

			jQuery('.feed-response-modal').html('');

			let feed_id   = $(this).attr("data-attr");
			let seller_id = urlParams.get('seller_id') ? urlParams.get('seller_id') : '';
			if (seller_id == '') {
				console.log('Seller Id is missing!');
				return false;
			}

			jQuery.ajax(
				{
					type: 'post',
					url: ajaxUrl,
					data: {
						ajax_nonce: ajaxNonce,
						feed_id: feed_id,
						seller_id: seller_id,
						mode: urlParams.get('mode'),
						action: 'ced_amazon_view_feed_response'
					},
					success: function (response) {
						ced_hide_loader();

						jQuery('.feed-response-modal').append(response.data);

						var modal           = document.getElementById("feedViewModal");
						modal.style.display = "block";

					}
				}
			);

		}
	);

	jQuery(document).on(
		'click',
		'.ced_feed_cancel',
		function (e) {
			var modal           = document.getElementById("feedViewModal");
			modal.style.display = "none";

		}
	)

	jQuery(document).on(
		'click',
		'.ced_template_cancel',
		function (e) {
			var modal           = document.getElementById("uploadTemplateModal");
			modal.style.display = "none";

		}
	)

	function escapeBrackets(str) {
		// Use regular expression to find and escape brackets
		return str.replace(/[(){}\[\]]/g, '\\$&');
	}

	function customSwal(swalObj = {}, callback, time = 250000) {

		window.scrollTo(0, 0);
		let notice = "";

		let title = swalObj.title ? swalObj.title : '';
		let text  = swalObj.text ? swalObj.text : '';

		if (swalObj.icon == "success") {

			notice += "<div  class='notice notice-success'><p> <b>" + title + "</b>. " + text + " </p></div>";

			if ($(".notice-success").length == 0) {
				$("#wpbody-content").prepend(notice);
			} else {
				$("#wpbody-content").find('.notice-success').html('<p><b>' + title + '</b>. ' + text + '</p>')
			}

		} else if (swalObj.icon == 'error') {

			notice += "<div  class='notice notice-error'><p> <b>" + title + "</b>. " + text + " </p></div>";

			if ($(".notice-error").length == 0) {
				$("#wpbody-content").prepend(notice);
			} else {
				$("#wpbody-content").find('.notice-error').html('<p><b>' + title + '</b>. ' + text + '</p>')
			}

		} else if (swalObj.icon == 'warning') {

			notice += "<div class='notice notice-warning'><p> <b>" + title + "</b>. " + text + " </p></div>";

			if ($(".notice-warning").length == 0) {
				$("#wpbody-content").prepend(notice);
			} else {
				$("#wpbody-content").find('.notice-warning').html('<p><b>' + title + '</b>. ' + text + '</p>')
			}

		}

		setTimeout(
			() => {
				$("#wpbody-content").find('.notice').remove();
				callback();
			},
			time
		)

	}

	jQuery(document).on(
		'click',
		'.ced-settings-checkbox',
		function (e) {
			if ($(this).parent('label').hasClass('is-checked')) {
				$(this).parent('label').removeClass('is-checked');
				$(this).removeAttr('value', 'off')
			} else {
				$(this).parent('label').addClass('is-checked');
				$(this).attr('value', 'on')
			}

		}
	);

	jQuery(document).on(
		'click',
		'.ced-asin-sync-toggle-select, .ced-shipment-sync-toggle-select',
		function (e) {

			let className = $(e.target).attr('id') == 'inspector-toggle-control-0 ced_auto_asin_sync' ? '.ced_amazon_catalog_asin_sync_meta_row' : '.ced_amazon_shipment_sync_meta_row';
			$(className).toggle()

		}
	);

	$(document).on(
		'click',
		'.woocommerce-importer-done-view-errors-amazon',
		function () {
			$('.wc-importer-error-log-amazon').slideToggle('slow');
			return false;
		}
	);

	$(document).on(
		'click',
		'.save_profile_button',
		function (e) {

			let checkList = {

				'profile_name' : {
					"class": '.profile_name_section .profile_name',
					'title':'Profile name',
					'text': 'Please fill the profile name.',
				},
				'woo_val' : {
					"class": '.wooCategories',
					'title': 'WooCommerce Category',
					'text': 'Please select a WooCommerce category.',
					
				},
				'ced_browse_category' : {
					"class": '.ced_browse_category',
					'title': 'Amazon Category',
					'text':  'Please select an Amazon category.',
				},
				'product_type' : {
					"class": '.ced_product_type',
					'title': 'Amazon Product Type',
					'text': 'Please select amazon product type.',
				}
				
			} 
			
			for (let n of Object.values(checkList)) {
				let name = $(n.class).val();
			
				if (!name || name.length === 0) {
					if (typeof event !== 'undefined') {
event.preventDefault();
					}
			
					customSwal({
						title: n.title,
						text: n.text,
						icon: 'error',
					});
			
					break; 
				}
			}
			
		}
	);

	jQuery(document).on(
		'click',
		'.ced-amz-profile-clone',
		function (e) {

			let woo_used_categories = $(this).attr('data-woo-used-cat');
			let woo_all_categories  = $(this).attr('data-woo-all-cat');

			woo_used_categories = JSON.parse(woo_used_categories);
			woo_all_categories  = JSON.parse(woo_all_categories);

			if (woo_all_categories.sort().join(',') === woo_used_categories.sort().join(',')) {

				customSwal(
					{
						title: 'Woocommerce Category',
						text: 'All existing woocommerce category are already mapped. Please create new woocommerce category or remove some woocommerce category from existed mapped profiles.',
						icon: 'error',
					}
				)
				return;

			}

			let template_id = $(this).data('clone_tmp_id');
			$('.ced-amz-clone-tmp').attr('clone_tmp_id', template_id)

			var modal           = document.getElementById("cloneTemplateModal");
			modal.style.display = "block";

		}
	);

	jQuery(document).on(
		'click',
		'.ced_clone_modal_cancel',
		function (e) {

			var modal           = document.getElementById("cloneTemplateModal");
			modal.style.display = "none";

			$('.ced_modal_notice').remove();

			$('#cloneTemplateModal').find('.wooCategories').selectWoo('destroy');
			$('#cloneTemplateModal').find('.wooCategories').val([]);
			$('#cloneTemplateModal').find('.wooCategories').selectWoo();

			$('.ced_amazon_clone_template_button').removeAttr('disabled')

			let refreshPage = $(this).attr("refresh");

			if (refreshPage == "true") {
				window.location.reload();
			}

		}
	)

	jQuery(document).on(
		'click',
		'.ced_amazon_clone_template_button',
		function (e) {
			e.preventDefault();
			let template_id = $('.ced-amz-clone-tmp').attr('clone_tmp_id');

			if (notNullAndEmpty(template_id)) {

				let woo_cat = $('#cloneTemplateModal').find('.wooCategories').val();

				if (woo_cat.length <= 0) {

					customModalSwal(
						{
							title: 'Error',
							text: 'Please select atleast one WooCommerce category.',
							icon: 'error',
						},
						() => { },
						'',
						'class',
						'ced-amz-clone-tmp',
						true
					);
					return;

				}


				let name = $('#cloneTemplateModal').find('.clone_template_name').val();

				if (name.length <= 0) {

					customModalSwal(
						{
							title: 'Error',
							text: 'Please enter a template name.',
							icon: 'error',
						},
						() => { },
						'',
						'class',
						'ced-amz-clone-tmp',
						true
					);
					return;

				}

				ced_display_loader()

				jQuery.ajax(
					{
						type: 'post',
						url: ajaxUrl,
						dataType: 'html',
						data: {
							user_id: user_id,
							seller_id: urlParams.get('seller_id'),
							ajax_nonce: ajaxNonce,
							template_id: template_id,
							name: name,
							woo_cat: woo_cat,
							mode: urlParams.get('mode'),
							action: 'ced_amazon_clone_template_modal'
						},
						success: function (response) {

							ced_hide_loader();

							response = JSON.parse(response);

							$('.ced-amz-clone-tmp').find('.notice').remove()
							if (response.success) {

								$('.ced_clone_modal_cancel').attr('refresh', "true");
								customModalSwal(
									{
										title: 'Success',
										text: response.data,
										icon: 'success',
									},
									() => { },
									'',
									'class',
									'ced-amz-clone-tmp',
									true
								)

								$('.ced_amazon_clone_template_button').attr('disabled', 'disabled');
							} else {
								customModalSwal(
									{
										title: 'Error',
										text: response.data,
										icon: 'error',
									}, () => { },  '', 'class', 'ced-amz-clone-tmp', true
								)
							}

						}

					}
				)

			} else {

				customModalSwal(
					{
						title: 'Error',
						text: 'Unable to Clone template currently. Please try again later.',
						icon: 'error',
					},
					() => { }, '', 'class', 'ced-amz-clone-tmp', true
				)
			}

		}
	)

	function customModalSwal(swalObj = {}, callback, time = '', tag_type, tag_name, is_dismissible) {

		let notice = "";

		let title = swalObj.title ? swalObj.title : '';
		let text  = swalObj.text ? swalObj.text : '';

		let tag = '';
		if (tag_type = 'class') {
			tag = '.' + tag_name;
		} else if (tag_type = 'id') {
			tag = '#' + tag_name;
		}

		let isDismissible = '';
		if (is_dismissible) {
			isDismissible = 'is-dismissible';
		}

		if (swalObj.icon == "success") {

			notice += "<div  class='ced_modal_notice notice notice-success " + isDismissible + " '><p> <b>" + title + "</b>. " + text + " </p></div>";

			if ($(".notice-success").length == 0) {
				$(tag).prepend(notice);
			} else {
				$(tag).find('.notice-success').html('<p><b>' + title + '</b>. ' + text + '</p>')
			}

		} else if (swalObj.icon == 'error') {

			notice += "<div  class='ced_modal_notice notice notice-error " + isDismissible + " '><p> <b>" + title + "</b>. " + text + " </p></div>";

			if ($(".notice-error").length == 0) {

				$('.ced-amz-clone-tmp').prepend(notice);
			} else {

				$(tag).find('.notice-error').html('<p><b>' + title + '</b>. ' + text + '</p>')
			}

		} else if (swalObj.icon == 'warning') {

			notice += "<div class='ced_modal_notice notice notice-warning " + isDismissible + " '><p> <b>" + title + "</b>. " + text + " </p></div>";

			if ($(".notice-warning").length == 0) {
				$(tag).prepend(notice);
			} else {
				$(tag).find('.notice-warning').html('<p><b>' + title + '</b>. ' + text + '</p>')
			}

		}

		if (time.length > 0) {

			setTimeout(
				() => {
					$(tag).find('.notice').remove();
					callback();
				},
				time
			)

		}

	}

	$(document).on(
		'keyup',
		'.ced_search_amz_categories_val',
		function (e) {
			$('.ced_search_amz_categories').prop('disabled', false);
		}
	)

	$(document).on(
		'click',
		'.ced_search_amz_categories',
		function (e) {
			e.preventDefault();

			let cat_value = $('.ced-woocommerce-category-search').find('input').val();

			if (cat_value.length <= 0) {

				customSwal(
					{
						title: 'Amazon Category',
						text: 'Please enter a valid value of Amazon category.',
						icon: 'error',
					}
				)
				return;

			}

			ced_display_loader()

			jQuery.ajax(
				{
					type: 'post',
					url: ajaxUrl,
					dataType: 'html',
					data: {
						user_id: user_id,
						seller_id: urlParams.get('seller_id'),
						ajax_nonce: ajaxNonce,
						cat_value: cat_value,
						action: 'ced_search_amz_categories'
					},
					success: function (response) {

						response = JSON.parse(response);

						$('.ced_primary_category').val('');
						$('.ced_secondary_category').val('');
						$('.ced_product_type').val('');
						$('.ced_amz_cat_name_arr').val('');
						$('.ced_product_type_name').val('');

						$('.ced_search_amz_categories').prop('disabled', true);
						ced_hide_loader();

						$('#ced_amazon_breadcrumb').text('');
						$("#ced_amazon_breadcrumb").css('display', 'none');

						$('.ced_template_required_attributes').html('');
						// $('.ced-category-mapping-wrapper').after(response.data);
						$('.ced-category-search-wrapper').after(response.data);
						$('.ced-or-wrapper').hide();
						$(this).attr('disabled', true);
						$('.ced-category-mapping-wrapper').hide();


					},

				}
			)

		}
	);


	$(document).on(
		'click',
		'.ced_amz_del_tem',
		function (e) {

			let template_id = $(this).attr('data-id');

			jQuery.ajax({
				type: 'post',
				url: ajaxUrl,
				dataType: 'html',
				data: {
					template_id: template_id,
					seller_id: urlParams.get('seller_id'),
					ajax_nonce: ajaxNonce,
					action: 'ced_amz_del_tem'
				},
				success: function (response) {

					ced_hide_loader();
					response = JSON.parse( response );
					customSwal(
						{
							title: 'Template',
							text: response.message,
							icon: response.status ? 'success' : 'error',
						}, () => { window.location.reload(); }
					)
					return;
				},

			})
	
		}
	)

		
	function removeURLParameter(url, parameterName) {
		var urlParts = url.split('?');

		if (urlParts.length >= 2) {
			var prefix = encodeURIComponent(parameterName) + '=';
			var params = urlParts[1].split(/[&;]/g);

			for (var i = params.length; i-- > 0;) {
				if (params[i].lastIndexOf(prefix, 0) !== -1) {
					params.splice(i, 1);
				}
			}

			return urlParts[0] + (params.length > 0 ? '?' + params.join('&') : '');
		}

		return url;
	}

	function ced_display_loader() {
		$('#wpbody-content').block(
			{
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			}
		);
	}

	function ced_hide_loader() {
		$('#wpbody-content').unblock();
	}

	$(document).on(
		'click',
		'.ced_amazon_selected_srh_cat',
		function (e) {
			e.preventDefault();

			$('.ced_amazon_selected_srh_cat').removeClass('ced_srh_selected_cat_bg');
			$('.ced_template_required_attributes').html('');

			let text = $(this).text();
			$(this).addClass('ced_srh_selected_cat_bg');

			$('#ced_amazon_breadcrumb').text('');
			$('#ced_amazon_breadcrumb').text(text);
			$("#ced_amazon_breadcrumb").css('display', 'block');

			let category_data = $(this).attr('data-category');
			let browseNodeId  = $(this).attr('data-browseNodeId');

			browseNodeId = browseNodeId.replaceAll('"', "");

			category_data    = JSON.parse(category_data);
			let categoryData = {
				primary_category: category_data['primary-category'],
				secondary_category: category_data['sub-category'],
				browse_nodes: browseNodeId
			}

			$('.ced_primary_category').val(categoryData['primary_category']);
			$('.ced_secondary_category').val(categoryData['secondary_category']);
			$('.ced_product_type').val(categoryData['browse_nodes']);

			$('.ced_product_type_name').val($(this).data('name'));
			$('.ced_amz_cat_name_arr').val($(this).text());

			ced_display_loader()

			let categoryResponse = ced_amazon_fetch_next_level_category( categoryData, '', 'no' );
			categoryResponse.then(
				response => {
					ced_hide_loader()
					handleCategoryResponse(response)
				}
			)

		}
	);

	jQuery(document).on(
		'change',
		'#ced_amazon_order_currency',
		function (e) {

			if ($(this).is(":checked")) {
				$('.ced_amz_currency_convert_row').css('display', '')
			} else {
				$('.ced_amz_currency_convert_row').css('display', 'none')
			}

		}
	)

	jQuery(document).on(
		'change',
		'#ced_amz_manual_curr_change',
		function (e) {

			if ($(this).is(":checked")) {
				$('.ced_amazon_currency_convert_rate_container').css('display', 'flex');
				$('.ced_amazon_currency_conversion_plugin').css('display', 'none');
			} else {
				$('.ced_amazon_currency_conversion_plugin').css('display', '');
				$('.ced_amazon_currency_convert_rate_container').css('display', 'none');
			}

		}
	)

	jQuery(document).on(
		'change',
		'#ced_amz_auto_curr_change',
		function (e) {

			if ($(this).is(":checked")) {
				$('.ced_amazon_currency_conversion_plugin').css('display', '');
				$('.ced_amazon_currency_convert_rate_container').css('display', 'none');

			} else {
				$('.ced_amazon_currency_conversion_plugin').css('display', 'none');
				$('.ced_amazon_currency_convert_rate_container').css('display', 'flex');
			}

		}
	)


	jQuery(document).on(
		'click',
		'.update-productType-btn',
		function (e) {

			ced_display_loader();

			jQuery.ajax({
				type: 'post',
				url: ajaxUrl,
				dataType: 'html',
				data: {
					user_id: user_id,
					seller_id: urlParams.get('seller_id'),
					ajax_nonce: ajaxNonce,
					action: 'ced_amz_update_product_types'
				},
				success: function (response) {

					ced_hide_loader();
					response = JSON.parse( response );
					customSwal(
						{
							title: 'Product Type Update',
							text: response.message,
							icon: response.status ? 'success' : 'error',
							}
					)
					return;
				},

				}
			)

		}
	)


	jQuery(document).on(
		'click',
		'.editinline',
		function (e) {

			let post_id = $( this ).closest( 'tr' ).attr( 'id' );
			post_id     = post_id.replace( 'post-', '' );
			$('.ced_amz_validation_issues' ).css('display', 'none');
			
			setTimeout( () =>{
				$('.ced_amz_validation_issues_' + post_id ).first().css('display', 'block');
			}, 100 )

			

		}
	)



	$(document).ready(
		function () {

			var breadCrumbArr = [];
			var lastLevalCat  = [];
			// Function to handle the change event of the select element
			$(document).on(
				'click',
				'.ced_amazon_category_arrow',
				function () {

					var selectedValue = $(this).data('id');
					var name          = $(this).data('name');
					var level         = $(this).data('level');

					ced_display_loader();
					$.ajax(
						{

							url: ajaxUrl,
							data: {
								ajax_nonce: ajaxNonce,
								action: 'ced_amazon_get_selected_categories',
								mode: urlParams.get('mode'),
								user_id: urlParams.get('user_id'),
								seller_id: urlParams.get('seller_id'),
								option: selectedValue

							},
							type: 'POST',
							success: function (response) {
								ced_hide_loader();
								if (!response.success) {
									if ($('.notice-error').length > 0) {

										$('.notice-error').html('<p>Unable to fetch categories. Try again later.</p>')
									} else {
										$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>Unable to fetch categories. Try again later.</p></div>')
										return;
									}

								}
								$('#ced_amazon_cat_header').html("");
								$('#ced_amazon_cat_header').html(
									"<span data-level='" + level + "' class='dashicons dashicons-arrow-left-alt2 ced_amazon_prev_category_arrow' \
                                data-id='" + selectedValue + "'' data-name='" + name + "'' class='dashicons dashicons-arrow-right-alt2'></span><strong id='ced_cat_label'>" + name + " </strong>"
								);

								breadCrumbArr.push(name);
								ced_amazon_update_breadCrumb();

								let olList = ced_amazon_add_next_level_cat(response, level, name);

							},
							error: function (xhr, status, error) {
								ced_hide_loader();;
								if ($('.notice-error').length > 0) {
									$('.notice-error').html('<p>Unable to fetch categories. Try again later.</p>')
								} else {
									$(document).find('form').prepend('<div class="notice notice-error is-dismissible"><p>Unable to fetch categories. Try again later.</p></div>')
									return;
								}

							}
						}
					);
				}
			);

			$(document).on(
				'click',
				'.ced_amazon_prev_category_arrow',
				function () {
					let level = $(this).attr('data-level');

					if (level <= 0) {
						return;
					}
					$(this).attr('data-level', (parseInt(level) - 1));
					$('.ced_amz_categories').each(
						function () {
							$(this).hide();
						}
					);
					$('#ced_amz_categories_' + level).show();

					let label = $("#ced_amz_categories_" + level).attr('data-node-value');
					$("#ced_cat_label").text(label);

					if (lastLevalCat.length >= 0) {
						lastLevalCat.pop();
					}

					breadCrumbArr.pop(label);
					ced_amazon_update_breadCrumb();

					return;

				}
			);

			$(document).on(
				'click',
				'.ced_amz_child_category',
				function () {
					let val = $(this).find('input').val();
					if (val.length <= 0) {
						lastLevalCat.push(val)
						ced_amazon_update_breadCrumb()
					} else {
						lastLevalCat.pop();
						ced_amazon_update_breadCrumb();
						lastLevalCat.push(val);
						ced_amazon_update_breadCrumb();
					}

				} 
			)

			function ced_amazon_add_next_level_cat(response, level = 1, name) {

				// let data = (response && response['response'] && response['response'].length > 0) ? response['response'] : [];

				let data                     = (response && response['data'] && response['data'].length > 0) ? response['data'] : [];
				let next_level               = level + 1;
				var hasChildrenElementOption = "";
				var html                     = "";
				var element_class            = '';
				let categoryData             = '';
				let browseNodeId             = '';

				html           += '<ol id="ced_amz_categories_' + next_level + '" class="ced_amz_categories" data-level="' + next_level + '" data-node-value="' + name + '">';
				Object.keys(data)?.map(
					(val) => {
						let ids = data[val]?.parent_id.join(',');
						if (data[val]?.hasChildren) {
							element_class            = 'ced_amazon_category_arrow';
							hasChildrenElementOption = "<span \ class='dashicons dashicons-arrow-right-alt2 \
					' \
					\
					\
					\
					\
					class='dashicons dashicons-arrow-right-alt2'>\
                    </span>";
						} else {
							element_class            = 'ced_amz_child_category';
							categoryData             = JSON.stringify(data[val].category);
							browseNodeId             = JSON.stringify(data[val].browseNodeId);
							hasChildrenElementOption = '<input type="radio" name="ced_amazon_last_level_cat" id="ced_amazon_last_level_cat" value="' + data[val]?.name + '" />';
						}
						html += "<li  data-browseNodeId='" + browseNodeId + "' data-category='" + categoryData + "'  class='" + element_class + "' data-name='" + data[val]?.name + "' data-children=" + data[val]?.hasChildren + " id='" + ids + "' data-id=" + ids + " data-level=" + next_level + ">" + data[val].name + "" + hasChildrenElementOption + "</li>"

					}
				);
				html += '</ol>';
				$('#ced_amz_categories_' + (level + 1)).remove();
				$('#ced_amz_categories_' + level).after(html);
				$('#ced_amz_categories_' + level).hide();

			}

			function ced_amazon_update_breadCrumb() {
				var breadCrumbHtml = "";

				for (var i = 0; i < breadCrumbArr.length; i++) {

					if (i === 0) {
						breadCrumbHtml += breadCrumbArr[i];
					} else {
						breadCrumbHtml += " > " + breadCrumbArr[i];

					}

				}
				if (lastLevalCat.length > 0) {
					breadCrumbHtml += " > " + lastLevalCat.join(" ");
				}
				if (breadCrumbArr.length > 0) {
					$("#ced_amazon_breadcrumb").css('display', 'block');
				} else {
					$("#ced_amazon_breadcrumb").css('display', 'none');
				}

				$("#ced_amazon_breadcrumb").text(breadCrumbHtml);
				$(".ced_amz_cat_name_arr").val(breadCrumbHtml);
			}

		}
	);


	$(document).on(
		'click',
		'.ced_amz_child_category',
		function (e) {

			// let template_id   = urlParams.get('template_id') ? urlParams.get('template_id') : '';
			let category_data = $(this).attr('data-category');
			let browseNodeId  = $(this).attr('data-browseNodeId');

			$(this).find('input').attr('checked', 'checked');

			browseNodeId = browseNodeId.replaceAll('"', "");

			/** To mange country SA */
			if ( category_data !== 'undefined' ) {
				category_data = JSON.parse(category_data);
			}
			
			let categoryData = {
				browse_nodes: browseNodeId
			}

			if ( category_data !== 'undefined' ) {
				category_data['primary_category']   = category_data['primary-category'];
				category_data['secondary_category'] = category_data['sub-category'];
			}

			$('.ced_primary_category').val(categoryData['primary_category']);
			$('.ced_secondary_category').val(categoryData['secondary_category']);
			$('.ced_browse_category').val(categoryData['browse_nodes']);
			$('.ced_browse_node_name').val($(this).text());

			$('.ced-product-type-mapping-wrapper').show();

		}
	);


	$(document).on(
		'click',
		'.ced_amz_product_type',
		function (e) {

			$(this).find('input').attr('checked', 'checked');

			let template_id = urlParams.get('template_id') ? urlParams.get('template_id') : '';
		
			let product_type      = $(this).attr('data-name');
			let product_type_name = $(this).text();

			$('.ced_product_type').val( product_type );
			$('.ced_product_type_name').val( product_type_name );

			let categoryData = {

				primary_category: $('.ced_primary_category').val(),
				secondary_category: $('.ced_secondary_category').val(),
				browse_nodes: $('.ced_browse_category').val(),
				product_type: product_type,
				product_type_name: product_type_name
				
			}
			
			$('.ced-product-type-mapping-wrapper-breadcrumb').find('#ced_amazon_breadcrumb').text( product_type_name );
			$('.ced-product-type-mapping-wrapper-breadcrumb').find('#ced_amazon_breadcrumb').show()

			ced_display_loader();

			$('.ced_template_required_attributes').html('');

			let categoryResponse = ced_amazon_fetch_next_level_category( categoryData, template_id, 'no' );
			
			categoryResponse.then(
				response => {
					ced_hide_loader();
					handleCategoryResponse(response)

					var browseNodesValue = $('.ced_browse_category').val(); 
					$('#recommended_browse_nodes').val(browseNodesValue).trigger('change'); 

				}
			)

		}

	);


	$(document).on( 'click', '.ced_amz_imported_orders', function (e) {

		let id = $(this).data('amz-order-id')
		
		if ( id.length <= 0 || id == null ) {
			customSwal(
				{
					title: 'Order id:',
					text: "Unable to get order id.",
					icon: 'error',
				}
			)
			return;
		}

		ced_display_loader();

		jQuery.ajax({
			type: 'post',
			url: ajaxUrl,
			dataType: 'html',
			data: {
				id: id,
				seller_id: urlParams.get('seller_id'),
				ajax_nonce: ajaxNonce,
				action: 'ced_amz_update_imported_orders'
			},
			success: function (response) {

				ced_hide_loader();
				response = JSON.parse( response );
				customSwal(
					{
						title: 'Order id',
						text: response.message,
						icon: response.status ? 'success' : 'error',
					}, () => { 
						window.location.reload();
					}, 5000
				)
				
			},

			}
		)

	});


	$(document).on('click', '.ced_feed_product_table_heading', function() {
		const $content = $(this).next('.accordion-content');
		const $arrow   = $(this).find('.arrow-icon');

		if ($content.is(':visible')) {
			// If content is visible, hide it
			$content.slideUp(200);
			$arrow.removeClass('rotated');
		} else {
			// If content is hidden, show it
			$content.slideDown(200);
			$arrow.addClass('rotated');
		}
	});


})(jQuery);
