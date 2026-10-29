/**
 * External films stay a poster until the visitor allows the provider.
 * The iframe is only ever youtube-nocookie.com or player.vimeo.com.
 */
(function () {
	var KEYS = {youtube: 'video_consent_youtube', vimeo: 'video_consent_vimeo'};

	function providerOf(value) {
		var key = String(value || '').toLowerCase();
		return key === 'youtube' || key === 'vimeo' ? key : '';
	}

	function storageKey(provider) {
		return KEYS[provider] || '';
	}

	function read(provider) {
		var key = storageKey(provider);
		if (!key) return null;
		try {
			var data = JSON.parse(window.localStorage.getItem(key) || 'null');
			return data && data.consent === true ? data : null;
		} catch (e) {
			return null;
		}
	}

	function allowed(provider) {
		var memory = window.__videoConsentMemory || {};
		return memory[provider] === true || !!read(provider);
	}

	function write(provider) {
		var key = storageKey(provider);
		if (!key) return;
		window.__videoConsentMemory = window.__videoConsentMemory || {};
		window.__videoConsentMemory[provider] = true;
		try {
			window.localStorage.setItem(key, JSON.stringify({consent: true, date: new Date().toISOString()}));
		} catch (e) { /* private mode */ }
	}

	function clear(provider) {
		var key = storageKey(provider);
		if (!key) return;
		window.__videoConsentMemory = window.__videoConsentMemory || {};
		window.__videoConsentMemory[provider] = false;
		try {
			window.localStorage.removeItem(key);
		} catch (e) { /* private mode */ }
	}

	function allowedSrc(src) {
		try {
			var url = new URL(src, window.location.origin);
			if (url.protocol !== 'https:') return '';
			var host = url.hostname.toLowerCase().replace(/^www\./, '');
			if (host === 'youtube-nocookie.com' || host === 'player.vimeo.com') return url.toString();
		} catch (e) { /* ignore */ }
		return '';
	}

	function withAutoplay(src, provider) {
		try {
			var url = new URL(src);
			url.searchParams.set('autoplay', '1');
			if (provider === 'youtube') {
				url.searchParams.set('playsinline', '1');
				url.searchParams.set('mute', '0');
			}
			if (provider === 'vimeo') {
				url.searchParams.set('muted', '0');
				url.searchParams.set('autopause', '0');
			}
			return allowedSrc(url.toString()) || src;
		} catch (e) {
			return src;
		}
	}

	function mount(placeholder, autoplay) {
		if (!placeholder || placeholder.dataset.extLoaded === '1') return;
		var src = allowedSrc(placeholder.getAttribute('data-embed-src') || '');
		if (!src) return;
		var provider = providerOf(placeholder.getAttribute('data-provider'));
		placeholder.dataset.extLoaded = '1';
		placeholder.classList.add('is-loaded');
		var player = document.createElement('div');
		player.className = 'extVideo__player';
		var iframe = document.createElement('iframe');
		iframe.setAttribute('title', placeholder.getAttribute('data-embed-title') || 'Video');
		iframe.setAttribute('allowfullscreen', '');
		iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
		iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		iframe.setAttribute('frameborder', '0');
		iframe.src = autoplay ? withAutoplay(src, provider) : src;
		player.appendChild(iframe);
		placeholder.replaceChildren(player);
	}

	function load(provider, autoplayTarget) {
		var name = providerOf(provider);
		if (!name || !allowed(name)) return;
		document.querySelectorAll('[data-ext-video][data-provider="' + name + '"]').forEach(function (el) {
			if (el.getAttribute('data-ext-scope') !== 'view') mount(el, autoplayTarget === el);
		});
	}

	function sync(root) {
		var scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll('[data-video-consent-panel] [data-consent-provider]').forEach(function (block) {
			var on = allowed(block.getAttribute('data-consent-provider'));
			block.querySelectorAll('[data-consent-on]').forEach(function (el) { el.hidden = !on; });
			block.querySelectorAll('[data-consent-off]').forEach(function (el) { el.hidden = on; });
		});
	}

	function grant(provider, source) {
		var name = providerOf(provider);
		if (!name) return;
		var placeholder = source && source.closest ? source.closest('[data-ext-video]') : null;
		if (placeholder && placeholder.getAttribute('data-ext-scope') === 'view') {
			mount(placeholder, true);
			return;
		}
		var x = window.scrollX;
		var y = window.scrollY;
		write(name);
		sync();
		load(name, source && source.closest ? source.closest('[data-ext-video]') : null);
		window.scrollTo(x, y);
	}

	function revoke(provider) {
		var name = providerOf(provider);
		if (!name) return;
		clear(name);
		window.location.reload();
	}

	function bind() {
		if (window.__extVideoBound) return;
		window.__extVideoBound = true;
		document.addEventListener('click', function (event) {
			var target = event.target instanceof Element ? event.target : event.target && event.target.parentElement;
			if (!target) return;
			var activate = target.closest('[data-ext-activate]');
			if (activate) {
				event.preventDefault();
				event.stopPropagation();
				grant(activate.getAttribute('data-ext-activate'), activate);
				return;
			}
			var button = target.closest('[data-ext-revoke]');
			if (button) {
				event.preventDefault();
				event.stopPropagation();
				revoke(button.getAttribute('data-ext-revoke'));
			}
		}, true);
	}

	window.initExternalVideos = function (root) {
		bind();
		sync(root);
		['youtube', 'vimeo'].forEach(function (provider) {
			if (allowed(provider)) load(provider);
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { window.initExternalVideos(); });
	} else {
		window.initExternalVideos();
	}
})();
