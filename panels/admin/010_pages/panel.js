/* global sys,c,p,t,l */
(function () {
	var setup = {
		info: 'pagePanel class extends cmsPanel class', // debug
		route: 'panels.admin.pages',
		extends: 'panels.admin'
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var pagesModule = this; // internal instance name

        pagesModule.reviewSiteRename = event => {
            const section=event.element.closest('.siteRename');
            const name=section.querySelector('input[name="siteName"]').value.trim().toLowerCase();
            const previous=section.dataset.currentName;
            if (!name || name === previous) return;
            const review='Rename '+previous+' to '+name+'?\n\nThis updates the site record, renames sites/'+previous+'/ to sites/'+name+'/ and updates this machine’s links and host rules.\nOther installations and DNS are not changed.';
            if (!window.confirm(review)) return;
            event.data.values = Object.assign({}, event.data.values, {siteName:name});
            event.executeAction('renameSite');
        };

		pagesModule.initFooterResize = function () {
			c('called').open();
		};

		var parentDraginitFooterHeight = pagesModule.draginitFooterHeight;
		pagesModule.draginitFooterHeight = event => {
			parentDraginitFooterHeight(event);
		};

		var treeUi = sys.pagesTreeUi || (sys.pagesTreeUi = {activeId: null});
        var initialActive = document.querySelector('.cms ul.tree li.active');
        if (initialActive && treeUi.activeId === null) {
            treeUi.activeId = initialActive.dataset.node_id;
        }
		function revealTreeRename() {
			const field = document.activeElement;
			if (!field?.matches('.pylon .tree [contenteditable="plaintext-only"]')) return;
			const tree = field.closest('div.tree');
			const row = field.getBoundingClientRect();
			const bounds = tree.getBoundingClientRect();
			const toolbar = tree.previousElementSibling.getBoundingClientRect();
			const viewport = window.visualViewport;
			const bottom = Math.min(bounds.bottom, viewport ? viewport.offsetTop + viewport.height : innerHeight) - 12;
			const top = Math.max(bounds.top, toolbar.bottom) + 8;
			tree.style.setProperty('--pages-rename-occlusion', Math.max(0, bounds.bottom - bottom) + 'px');
			if (row.bottom > bottom) tree.scrollTop += row.bottom - bottom;
			else if (row.top < top) tree.scrollTop -= top - row.top;
		}
		const revealAfterViewportChange = () => requestAnimationFrame(revealTreeRename);
		window.visualViewport?.addEventListener('resize', revealAfterViewportChange);
		window.visualViewport?.addEventListener('scroll', revealAfterViewportChange);
		window.addEventListener('resize', revealAfterViewportChange);
		document.addEventListener('scroll', event => {
			if (event.target.matches?.('body.pages > main > .pylon > nav > .tree')) {
				event.target.parentElement.classList.toggle('cmsHeaderScrolled', event.target.scrollTop > 1);
			}
		}, true);
		pagesModule.onUpdateTree = function () {

            requestAnimationFrame(revealTreeRename);
            const foldFocus = treeUi.foldFocus;
            delete treeUi.foldFocus;
            if (foldFocus && performance.now() - foldFocus.time < 5000 &&
                (document.activeElement === document.body || document.activeElement === foldFocus.element || document.activeElement?.dataset.focusId === foldFocus.id)) {
                document.querySelector('a.fold[data-focus-id="' + CSS.escape(foldFocus.id) + '"]')?.focus({preventScroll:true});
            }
			var active = document.querySelector('.cms ul.tree li.active');
			if (!active || typeof scrollTree !== 'function') {
				return;
			}
			var id = active.getAttribute('data-node_id');
			var selected = treeUi.activeId !== null && id !== treeUi.activeId;
            treeUi.activeId = id;
            if (selected) scrollTree('smooth', id, true);
		};

		// Focus the actual editor during the tap so iOS can open the keyboard.
		pagesModule.editLabel = event => {
			const id = event.element.closest('[data-id]')?.dataset.id;
			const buttons = id && document.querySelectorAll('div.pylon ul.tree li[data-node_id="' + CSS.escape(id) + '"] > label > button.label');
			const button = buttons && Array.from(buttons).find(candidate => candidate.getClientRects().length);
			if (!button) { sys.core.server.sendEventCall(event); return; }
			const wrapper = document.createElement('span');
			wrapper.className = 'treeLabelEdit';
			const icon = button.querySelector('i.type');
			if (icon) wrapper.append(icon.cloneNode(true));
			const field = document.createElement('span');
			field.className = 'label';
			field.setAttribute('contenteditable', 'plaintext-only');
			field.dataset.on_blur = 'updateLabel';
			field.dataset.on_keypress = 'checkKey';
			field.textContent = button.textContent.trim();
			wrapper.append(field);
			button.replaceWith(wrapper);
			sys.initEvents($(wrapper));
			closeAllMenus();
			field.focus({preventScroll: true});
			selectElementContents(field);
			revealTreeRename();
		};

		pagesModule.checkKey = e => {
			var key = e.e.originalEvent;
			if ((key.key === 'Enter' || key.keyCode === 13) && !key.shiftKey) {
				key.preventDefault();
				e.sourceElement.blur();
				return false;
			}
			return true;
		};
	});
})();

document.addEventListener('input', function (event) {
	var field = event.target;
	if (!field.closest || !field.closest('aside.cms')) {
		return;
	}
	if (field.name !== 'meta_description' && field.name !== 'meta_share_title') {
		return;
	}
	var count = field.parentElement && field.parentElement.querySelector('small span');
	if (count) {
		count.textContent = String(field.value.length);
	}
});

// Touch: the sidebar flag follows a swipe - to the left opens, to the right closes.
document.addEventListener('pointerdown', function (event) {
	var flag = event.pointerType !== 'mouse' && event.target.closest && event.target.closest('aside.cms a.sidebarFlag');
	if (!flag) {
		return;
	}
	var startX = event.clientX, startY = event.clientY;
	var move = function (e) {
		var dx = e.clientX - startX;
		if (Math.abs(dx) < 24 || Math.abs(dx) < Math.abs(e.clientY - startY)) {
			return;
		}
		end();
		var collapsed = flag.closest('aside.cms').classList.contains('collapsed');
		if (collapsed === dx < 0) {
			flag.click();
		}
		// the tap that ends the swipe must not toggle it back
		flag.addEventListener('click', swallow, {capture: true, once: true});
		setTimeout(function () {
			flag.removeEventListener('click', swallow, true);
		}, 400);
	};
	var swallow = function (e) {
		e.preventDefault();
		e.stopImmediatePropagation();
	};
	var end = function () {
		document.removeEventListener('pointermove', move, true);
		document.removeEventListener('pointerup', end, true);
		document.removeEventListener('pointercancel', end, true);
	};
	document.addEventListener('pointermove', move, true);
	document.addEventListener('pointerup', end, true);
	document.addEventListener('pointercancel', end, true);
}, true);

// The clipboard contains a media reference for this browser tab.
(function () {
	let clipboard, destination, statusTimer, beforeHold;
	const tileSelector = '.mmlContent li[data-drag_type="media"][data-media_id]';
	const storageKey = 'fluxo.pages.mediaClipboard:' + location.pathname.split('/admin/')[0];
	try { clipboard = JSON.parse(sessionStorage.getItem(storageKey)); } catch {}
	if (clipboard && !clipboard.token) clipboard.token = String(Date.now()) + Math.random();
	function updateClipboardStatus() {
		if (!document.body) return;
		if (clipboard) document.body.dataset.pageMediaCopied = clipboard.mediaShareImage === '1' ? 'image' : 'media';
		else document.body.removeAttribute('data-page-media-copied');
	}
	updateClipboardStatus();
	if (!document.body) document.addEventListener('DOMContentLoaded', updateClipboardStatus, {once: true});
	const mobile = () => document.body.classList.contains('cmsMobile');
	const shareTarget = () => document.querySelector('aside .mediaDrop.shareImage');

	function announce(message) {
		let status = document.querySelector('.pageMediaStatus');
		if (!status) {
			status = document.createElement('div');
			status.className = 'pageMediaStatus';
			status.setAttribute('role', 'status');
			document.body.append(status);
		}
		status.textContent = message;
		clearTimeout(statusTimer);
		statusTimer = setTimeout(() => status.remove(), 2200);
	}

	function accepts(tile, target) {
		const allowed = target.dataset.mediaAccept;
		if (!allowed || (tile.mediaShareImage === '1' && allowed.split(',').includes('.' + tile.mediaExtension))) return true;
		announce('Choose a JPG, PNG, GIF or WebP image.');
		return false;
	}

	function insert(tile, target, range, consume = false) {
		if (!target?.isConnected || !tile) return false;
		if (!accepts(tile, target)) return false;
		if (range && !target.contains(range.commonAncestorContainer)) return false;
		const event = new sys.core.event($.Event('click', {target, currentTarget: target}));
		event.collectElementData();
		if (range) event.mediaInsertionRange = range.cloneRange();
		if (consume) {
			if (range) event.onMediaInserted = () => consumeClipboard(tile.token);
			else event.data.dataset.media_clipboard = tile.token;
		}
		event.data.dragdata = {dataset: tile};
		event.executeAction(target.dataset.on_drop);
		return true;
	}

	function pick(target, range) {
		if (!target) return;
		const share = target.matches('.mediaDrop.shareImage');
		const sharePage = share ? target.closest('[data-node_id]')?.dataset.node_id : null;
		destination = {target, range, sharePage};
		document.body.toggleAttribute('data-page-share-picker', share);
		document.dispatchEvent(new Event('cms-open-media-picker'));
	}

	function setClipboard(value) {
		clipboard = value;
		try {
			if (value) sessionStorage.setItem(storageKey, JSON.stringify(value));
			else sessionStorage.removeItem(storageKey);
		} catch {}
		updateClipboardStatus();
		const previous = document.querySelector('.mmlContent [data-media-copied]');
		previous?.removeAttribute('data-media-copied');
		previous?.removeAttribute('data-save-state');
		const tile = value && document.querySelector(tileSelector + '[data-node_id="' + CSS.escape(value.node_id) + '"]');
		if (tile) {
			tile.setAttribute('data-media-copied', '');
			tile.setAttribute('data-save-state', 'saved');
		}
	}
	function consumeClipboard(token) {
		if (clipboard && clipboard.token === token) setClipboard(null);
	}
	window.consumePagesMediaClipboard = consumeClipboard;
	sys.pagesMedia = {
		pick,
		paste: (target, range) => insert(clipboard, target, range, true),
		hasClipboard: () => !!clipboard
	};

	document.addEventListener('cms-media-hold', event => {
		if (!mobile() || !event.target.matches(tileSelector)) return;
		const box = event.target.closest('.cmslightbox');
		if (event.detail === 'cancel') {
			setClipboard(beforeHold);
			window.cmsFeedback?.notice('', box);
		} else if (event.detail === 'start') {
			beforeHold = clipboard;
			const {node_id, drag_type, mediaExtension, mediaShareImage} = event.target.dataset;
			setClipboard({node_id, drag_type, mediaExtension, mediaShareImage, token: String(Date.now()) + Math.random()});
			const text = 'Copied to clipboard.';
			window.cmsFeedback?.notice(text, box);
		} else {
			beforeHold = null;
		}
	});
	document.addEventListener('click', event => {
		if (!mobile()) return;
		const tile = event.target.closest(tileSelector);
		if (tile && destination && tile.closest('.pageToolBox[data-page-tool="media"]:not([hidden])')) {
			event.preventDefault();
			event.stopImmediatePropagation();
			const {range, sharePage} = destination;
			const target = sharePage
				? document.querySelector('aside [data-node_id="' + CSS.escape(sharePage) + '"] .mediaDrop.shareImage')
				: destination.target;
			if (!target?.isConnected) { destination = null; return; }
			if (!accepts(tile.dataset, target)) return;
			destination = null;
			document.dispatchEvent(new Event('cms-close-media-picker'));
			insert({...tile.dataset}, target, range);
		} else if (event.target.closest('[data-page-media-picker], aside .mediaDrop.shareImage') && !event.target.closest('a, input, [data-on_click]')) {
			pick(shareTarget());
		} else if (event.target.closest('[data-page-media-paste]')) {
			insert(clipboard, shareTarget(), null, true);
		}
	}, true);
	document.addEventListener('cms-media-closed', () => {
		destination = null;
		document.body.removeAttribute('data-page-share-picker');
	});
})();

// Touch: the first tap on a thumb opens its overlay, a tap elsewhere closes it.
document.addEventListener('click', function (event) {
	if (document.documentElement.dataset.pointer !== 'touch') {
		return;
	}
	var thumb = event.target.closest && event.target.closest('div.mmlContent ul ul > li[data-drag_type]:not(.upload)');
	document.querySelectorAll('div.mmlContent li.touchOpen').forEach(function (li) {
		li !== thumb && li.classList.remove('touchOpen');
	});
	if (thumb && thumb.querySelector('.thumbActions') && !thumb.classList.contains('touchOpen')) {
		thumb.classList.add('touchOpen');
		event.preventDefault();
		event.stopPropagation();
	}
}, true);

function changeSrc(src) {
	const fresh=new URL(src,location.href);fresh.searchParams.set('_preview',Date.now());
	$(".srcToChange").attr('src',fresh.href);
}

function startCroping() {
	var $cnv_image = jQuery('img.srcToChange');
	var rect = $cnv_image[0].getBoundingClientRect();
	runImageEditor({
		domObj: $cnv_image,
		is_backgroundimage: false,
		src: $cnv_image.attr('src'),
		display_width: Math.round(rect.width),
		display_height: Math.round(rect.height),
		offset_left: rect.left + window.scrollX,
		offset_top: rect.top + window.scrollY
	});
}


var url = window.location.href;
var targetID = '' + url.match(/id=[^&]*/) + '';
var id = targetID.replace('id=', '');
if (id == '' || id == 'null' || !isset(url)) {
	id = $("div.cms .tree .active > div > .label").attr('data-node_id');
}
if (false && id !== '0') {
	var target = $("ul.tree li[data-node_id='" + id + "']");
	var targetTop = 0;
	if (isset(target.offset())) {
		targetTop = target.offset().top;
	}
	var parentTop = $('.tree').offset().top - $('.tree').scrollTop();
	$('.tree').animate({
		scrollTop: targetTop - parentTop - 100
	}, 1000, 'easeOutQuint');
}

function mountSkpStages(root) {
	(root || document).querySelectorAll('[data-skp-src]').forEach(function (stage) {
		if (stage.dataset.skpMounted === '1' || !stage.dataset.skpModule) {
			return;
		}
		stage.dataset.skpMounted = '1';
		import(stage.dataset.skpModule).then(function (mod) {
			return mod.mountSkpViewer(stage, stage.dataset.skpSrc);
		}).then(function (handle) {
			stage._skp = handle;
		}).catch(function () {
			stage.dataset.skpMounted = '';
		});
	});
}

function stopFileMedia(root) {
	(root || document).querySelectorAll('.fileEditor audio, .fileEditor video').forEach(function (media) {
		media.pause();
	});
	(root || document).querySelectorAll('[data-skp-src]').forEach(function (stage) {
		if (stage._skp && stage._skp.dispose) {
			stage._skp.dispose();
		}
	});
}

document.addEventListener('cms:rendered', function () {
	mountSkpStages();
});
mountSkpStages();

document.addEventListener('click', function (event) {
	var closer = event.target.closest && event.target.closest('.fileEditor header a, .fileEditor [data-action="deleteMedia"], .cmslightbox.upload header a');
	if (!closer) {
		return;
	}
	stopFileMedia(closer.closest('.cmslightbox') || document);
}, true);

// Fold links have no navigation destination: expose their existing click action to keyboards.
document.addEventListener('keydown', event => {
    if (!event.target.matches?.('a.fold[role="button"]') || event.repeat || event.isComposing) return;
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault(); event.target.focus({preventScroll:true});
        sys.pagesTreeUi.foldFocus = {id:event.target.dataset.focusId, element:event.target, time:performance.now()};
        event.target.click();
    }
});

document.addEventListener('focusin', event => {
    const pending = sys.pagesTreeUi?.foldFocus;
    if (pending && event.target !== pending.element && event.target.dataset?.focusId !== pending.id) delete sys.pagesTreeUi.foldFocus;
});
