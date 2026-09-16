/**
 * Intercept WooCommerce variation saves and send one variation per request.
 *
 * Save Changes and Update both call the same AJAX action. WordPress returns
 * HTTP 400 when that POST exceeds max_input_vars and `action` is truncated.
 * WooCommerce's save_changes lives in a private IIFE, so we wrap $.ajax.
 */
(function ($) {
	'use strict';

	var originalAjax = $.ajax;
	var CHUNK_SIZE = 1;

	function getOptions(url, options) {
		if (typeof url === 'object') {
			return url;
		}

		options = options || {};
		options.url = url;
		return options;
	}

	function isVariationSave(options) {
		var data = options && options.data;
		if (!data) {
			return false;
		}

		if (typeof data === 'string') {
			return data.indexOf('action=woocommerce_save_variations') !== -1;
		}

		return data.action === 'woocommerce_save_variations';
	}

	function getVariationIndices(data) {
		var postIds = data && data.variable_post_id;
		if (!postIds || typeof postIds !== 'object') {
			return [];
		}

		return Object.keys(postIds);
	}

	function pickIndexedValue(value, indexSet) {
		if (Array.isArray(value)) {
			var fromArray = {};
			var arrayHit = false;
			Object.keys(indexSet).forEach(function (idx) {
				if (typeof value[idx] !== 'undefined') {
					fromArray[idx] = value[idx];
					arrayHit = true;
				}
			});
			return arrayHit ? fromArray : value;
		}

		if (!value || typeof value !== 'object') {
			return value;
		}

		var keys = Object.keys(value);
		var allNumeric = keys.length > 0 && keys.every(function (key) {
			return /^\d+$/.test(key);
		});

		if (allNumeric) {
			var picked = {};
			var hit = false;
			keys.forEach(function (key) {
				if (indexSet[key]) {
					picked[key] = value[key];
					hit = true;
				}
			});
			return hit ? picked : value;
		}

		var nested = {};
		keys.forEach(function (key) {
			nested[key] = pickIndexedValue(value[key], indexSet);
		});
		return nested;
	}

	function chunkPayload(data, indices) {
		var indexSet = {};
		indices.forEach(function (idx) {
			indexSet[String(idx)] = true;
		});

		var chunk = {
			action: 'woocommerce_save_variations',
			security: data.security,
			product_id: data.product_id,
			'product-type': data['product-type']
		};

		$.extend(chunk, pickIndexedValue(data, indexSet));

		chunk.action = 'woocommerce_save_variations';
		chunk.security = data.security;
		chunk.product_id = data.product_id;

		return chunk;
	}

	function unblockProductData() {
		var $panel = $('#woocommerce-product-data');
		if ($panel.length && typeof $panel.unblock === 'function') {
			$panel.unblock();
		}
	}

	function alertFailure(status, body) {
		var snippet = body ? String(body).replace(/<[^>]+>/g, ' ').substring(0, 280) : '';
		window.alert(
			'Variation save failed (HTTP ' + status + '). ' +
			(snippet || 'WordPress never received the WooCommerce save action. Reload and try saving one newly edited variation at a time.')
		);
	}

	function saveChunks(options, chunks) {
		var deferred = $.Deferred();
		var lastResponse;

		function next(index) {
			if (index >= chunks.length) {
				if (typeof options.success === 'function') {
					options.success.call(options.context || options, lastResponse, 'success', deferred);
				}
				deferred.resolve(lastResponse);
				return;
			}

			originalAjax({
				url: options.url,
				type: options.type || 'POST',
				data: chunks[index]
			}).done(function (response) {
				lastResponse = response;
				next(index + 1);
			}).fail(function (xhr) {
				unblockProductData();
				alertFailure(xhr.status, xhr.responseText);
				if (typeof options.error === 'function') {
					options.error.call(options.context || options, xhr, 'error', xhr.statusText);
				}
				deferred.reject(xhr);
			});
		}

		next(0);
		return deferred.promise();
	}

	$.ajax = function (url, options) {
		options = getOptions(url, options);

		if (!isVariationSave(options) || typeof options.data !== 'object') {
			return originalAjax.apply(this, typeof url === 'object' ? [url] : arguments);
		}

		var indices = getVariationIndices(options.data);
		if (!indices.length) {
			options.data.action = 'woocommerce_save_variations';
			options.error = options.error || function (xhr) {
				unblockProductData();
				alertFailure(xhr.status, xhr.responseText);
			};
			return originalAjax.call(this, options);
		}

		var chunks = [];
		var i;
		for (i = 0; i < indices.length; i += CHUNK_SIZE) {
			chunks.push(chunkPayload(options.data, indices.slice(i, i + CHUNK_SIZE)));
		}

		return saveChunks(options, chunks);
	};
})(jQuery);
