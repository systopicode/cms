/* global sys */
/**
 * <fluxo-edit-bar> - the bar a signed-in editor sees on the website
 * (EditBar.php). Shadow DOM, so the site's css does not reach it and it does
 * not reach the site. Folded or not is kept in localStorage.
 */
(function () {
	var config = window.fluxoEditBar;
	if (!config || customElements.get('fluxo-edit-bar')) {
		return;
	}
	var icon = {
		pen: '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
		fold: '<path d="M15 6l-6 6 6 6"/>',
	};
	var fa = name => '<i class="fa-regular fa-' + name + '" aria-hidden="true"></i>';
	var svg = name => '<svg viewBox="0 0 24 24" aria-hidden="true">' + icon[name] + '</svg>';
	var blank = ' target="_blank" rel="noopener"';
	var esc = text => String(text).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[c]);

	customElements.define('fluxo-edit-bar', class extends HTMLElement {
		connectedCallback() {
			var root = this.attachShadow({mode: 'open'});
			root.innerHTML = '<link rel="stylesheet" href="' + esc(config.icons) + '"><style>' +
				':host{all:initial;position:fixed;left:16px;bottom:16px;z-index:2147483000;font:500 13px/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;--main:' + config.main + ';--high:' + config.highlight + ';--bg:' + config.bg + ';--onMain:' + config.onMain + ';--onHigh:' + config.onHighlight + '}' +
				'.bar{display:flex;align-items:center;gap:2px;padding:4px;border-radius:999px;background:var(--main);color:var(--onMain);box-shadow:0 6px 24px rgba(0,0,0,.25)}' +
				'button,a{all:unset;box-sizing:border-box;display:inline-flex;align-items:center;gap:7px;height:36px;padding:0 12px;border-radius:999px;cursor:pointer;color:inherit;white-space:nowrap}' +
				'button:hover,a:hover,button[aria-expanded=true]{background:color-mix(in srgb,var(--onMain) 14%,transparent)}' +
				'button:focus-visible,a:focus-visible{outline:2px solid var(--high);outline-offset:1px}' +
				'svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}' +
				'.menu i{width:18px;font-size:15px;text-align:center;flex:none;color:var(--main)}' +
				'.brand{width:36px;padding:0;justify-content:center}.brand img{width:22px;height:22px}' +
				'.edit{background:var(--high);color:var(--onHigh)}.edit:hover{background:var(--high);filter:brightness(1.08)}' +
				'.fold{width:30px;padding:0;justify-content:center;opacity:.7}' +
				':host([folded]) .edit,:host([folded]) .fold{display:none}' +
				'.menu{position:absolute;left:0;bottom:52px;min-width:230px;padding:6px;border-radius:14px;background:#fff;color:#1b1f22;box-shadow:0 12px 36px rgba(0,0,0,.22)}' +
				'.menu[hidden]{display:none}' +
				'.menu a{display:flex;width:100%;height:34px;border-radius:8px}.menu a:hover{background:var(--bg)}' +
				'.menu a.loading{position:relative;color:transparent;pointer-events:none}' +
				'.menu a.loading i{visibility:hidden}' +
				'.menu a.loading:before{content:"";position:absolute;left:18px;top:50%;width:12px;height:12px;margin-top:-7px;border-radius:50%;border:2px solid var(--main);border-top-color:transparent;animation:spin .6s linear infinite}' +
				'@keyframes spin{to{transform:rotate(360deg)}}' +
				'.menu .who{height:auto;gap:10px;padding:8px 10px;margin-bottom:6px;line-height:1.25}' +
				'.who img{width:30px;height:30px;border-radius:50%;object-fit:cover}.who small{display:block;color:color-mix(in srgb,var(--main) 55%,#888);font-weight:400;margin-top:2px}' +
				'hr{border:0;border-top:1px solid color-mix(in srgb,var(--main) 12%,#fff);margin:6px 4px}' +
				'.sections:empty{display:none}' +
				'@media print{:host{display:none}}' +
				'</style>' +
				'<div class="bar">' +
				'<button class="brand" type="button" aria-expanded="false" aria-label="fluxo menu" title="fluxo"><img src="' + esc(config.signet) + '" alt=""></button>' +
				'<a class="edit" href="#"' + blank + '>' + svg('pen') + '<span>Edit page</span></a>' +
				'<button class="fold" type="button" title="Fold the bar">' + svg('fold') + '</button>' +
				'<div class="menu" hidden>' +
				'<a class="who" href="' + esc(config.cms) + 'user/"' + blank + '><img src="' + esc(config.portrait) + '" alt=""><span>' + esc(config.name) + '<small>Your account</small></span></a>' +
				'<a href="' + esc(config.cms) + '"' + blank + '>' + fa('grid-2') + 'Dashboard</a>' +
				'<div class="sections"></div>' +
				'<hr><a class="out" href="#">' + fa('arrow-right-from-bracket') + 'Log out</a>' +
				'</div></div>';

			var brand = root.querySelector('.brand');
			var menu = root.querySelector('.menu');
			var sections = root.querySelector('.sections');
			var loaded = false;
			var fold = folded => {
				this.toggleAttribute('folded', folded);
				try { localStorage.setItem('fluxoEditBar', folded ? 'folded' : ''); } catch (e) { /* private mode */ }
			};
			var page = () => (sys && sys.singletons && sys.singletons.pages && sys.singletons.pages.renderer.__data && sys.singletons.pages.renderer.__data.page) || config.page;
			var showMenu = open => {
				menu.hidden = !open;
				brand.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (open && !loaded) {
					loaded = true;
					fetch(config.cms + 'editBarMenu', {headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'})
						.then(response => response.ok ? response.text() : '[]')
						.then(text => JSON.parse(window.sysdebug ? window.sysdebug.trimXhrResponse(text) : text))
						.then(items => {
							sections.innerHTML = items.map(item => '<a href="' + esc(item.href) + '"' + blank + '><i class="' + esc(item.icon || 'fa-regular fa-square') + '" aria-hidden="true"></i>' + esc(item.label) + '</a>').join('');
						})
						.catch(() => { loaded = false; });
				}
			};

			try { this.toggleAttribute('folded', localStorage.getItem('fluxoEditBar') === 'folded'); } catch (e) { /* private mode */ }
			brand.addEventListener('click', () => {
				if (this.hasAttribute('folded')) {
					fold(false);
					return;
				}
				showMenu(menu.hidden);
			});
			root.querySelector('.fold').addEventListener('click', () => {
				showMenu(false);
				fold(true);
			});
			root.querySelector('.edit').addEventListener('click', event => {
				var current = page();
				event.currentTarget.href = config.cms + 'pages/?id=' + encodeURIComponent(current.id) + '&lang=' + encodeURIComponent(current.lang);
			});
			root.querySelector('.out').addEventListener('click', event => {
				event.currentTarget.href = config.cms + 'logout?to=' + encodeURIComponent(location.pathname + location.search);
				event.currentTarget.classList.add('loading');
			});
			menu.addEventListener('click', event => {
				if (event.target.closest('a[target]')) {
					showMenu(false);
				}
			});
			document.addEventListener('click', event => {
				if (!event.composedPath().includes(this)) {
					showMenu(false);
				}
			});
			document.addEventListener('keydown', event => {
				if (event.key === 'Escape') {
					showMenu(false);
				}
			});
		}
	});
})();
