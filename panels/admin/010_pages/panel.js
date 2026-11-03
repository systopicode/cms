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
		pagesModule.onUpdateTree = function () {
			standIn && standIn.remove();
            standIn = null;
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

		// iOS opens the keyboard only for a focus within the tap, not after the
		// answer: a stand-in field takes it now and hands it to the label.
		var standIn = null;
		pagesModule.editLabel = event => {
			standIn = document.createElement('input');
			standIn.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;font-size:16px';
			document.body.appendChild(standIn);
			standIn.focus({preventScroll: true});
			sys.core.server.sendEventCall(event);
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
