(function () {
	'use strict';

	var config = window.WpFavoritesRuntime || {};

	if (!config.restUrl) {
		return;
	}

	function getState(button) {
		return button.getAttribute('data-is-favorite') === '1';
	}

	function setState(button, isFavorite) {
		button.setAttribute('data-is-favorite', isFavorite ? '1' : '0');
		button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
		button.classList.toggle('is-favorite', isFavorite);
		button.classList.toggle('is-not-favorite', !isFavorite);

		var svg = button.querySelector('.wp-favorites-toggle__svg');

		if (svg) {
			svg.setAttribute('fill', isFavorite ? 'currentColor' : 'none');
		}

		var label = button.querySelector('.wp-favorites-toggle__label');

		if (label) {
			var text = isFavorite
				? button.getAttribute('data-label-remove')
				: button.getAttribute('data-label-add');

			if (text !== null) {
				label.textContent = text;
			}
		}
	}

	function setStateEverywhere(postId, isFavorite) {
		document
			.querySelectorAll('[data-wp-favorites-toggle][data-post-id="' + postId + '"]')
			.forEach(function (button) {
				setState(button, isFavorite);
			});
	}

	function removeFromLoops(postId) {
		document.querySelectorAll('[data-post-id="' + postId + '"]').forEach(function (node) {
			var item = node.closest('[data-wp-favorites-item]');

			if (item) {
				item.remove();
			}
		});
	}

	function request(method, url, body) {
		var options = {
			method: method,
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.restNonce || ''
			}
		};

		if (body) {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(body);
		}

		return fetch(url, options).then(function (response) {
			if (!response.ok) {
				throw new Error('Request failed with status ' + response.status);
			}

			return response.json();
		});
	}

	function toggle(button) {
		if (button.getAttribute('data-busy') === '1') {
			return;
		}

		var postId = button.getAttribute('data-post-id');
		var wasFavorite = getState(button);
		var nextFavorite = !wasFavorite;

		button.setAttribute('data-busy', '1');
		setStateEverywhere(postId, nextFavorite);

		request('POST', config.restUrl, { post_id: Number(postId) })
			.then(function (data) {
				var isFavorite = !!data.isFavorite;

				setStateEverywhere(postId, isFavorite);

				if (!isFavorite) {
					removeFromLoops(postId);
				}

				if (config.refreshMode === 'reload') {
					window.location.reload();
					return;
				}

				document.dispatchEvent(
					new CustomEvent('wp-favorites:toggled', {
						bubbles: true,
						detail: {
							postId: Number(postId),
							isFavorite: isFavorite,
							ids: data.ids || []
						}
					})
				);
			})
			.catch(function (error) {
				setStateEverywhere(postId, wasFavorite);

				if (window.console && config.strings && config.strings.error) {
					window.console.warn(config.strings.error, error);
				}
			})
			.finally(function () {
				button.removeAttribute('data-busy');
			});
	}

	function sync() {
		if (!config.listUrl) {
			return;
		}

		request('GET', config.listUrl)
			.then(function (data) {
				var ids = (data.ids || []).map(Number);

				document.querySelectorAll('[data-wp-favorites-toggle]').forEach(function (button) {
					var postId = Number(button.getAttribute('data-post-id'));
					setState(button, ids.indexOf(postId) !== -1);
				});
			})
			.catch(function () {});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-wp-favorites-toggle]');

		if (!button) {
			return;
		}

		event.preventDefault();
		toggle(button);
	});

	// Re-sync when Bricks refreshes a query loop via AJAX.
	document.addEventListener('bricks/ajax/nodes_added', sync);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', sync);
	} else {
		sync();
	}
})();
