/* global sys,c,p,t,l */
(function () {
	var setup = {
		info: 'pagePanel class extends cmsPanel class', // debug
		route: 'panels.cms.pages',
		extends: 'panels.cms'
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var pagesModule = this; // internal instance name

		pagesModule.initFooterResize = function () {
			c('called').open();
		};

		var parentDraginitFooterHeight = pagesModule.draginitFooterHeight;
		pagesModule.draginitFooterHeight = event => {
			parentDraginitFooterHeight(event);
		};

		// A tree click replaces the tree markup, so the row that was clicked is
		// gone. This runs after the new markup is in the document. A new
		// selection is aligned like the crosshairs button; a fold or a rename
		// only moves the scroller when the selection fell out of view.
		var treeActiveId = null;
		pagesModule.onUpdateTree = function () {
			var active = document.querySelector('.cms ul.tree li.active');
			if (!active || typeof scrollTree !== 'function') {
				return;
			}
			var id = active.getAttribute('data-node_id');
			var selected = id !== treeActiveId;
			treeActiveId = id;
			scrollTree(selected ? 'smooth' : 'instant', id, !selected);
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

function changeSrc(src) {
	$(".srcToChange").attr('src', src);
}

function startCroping() {
	var $cnv_image = jQuery('img.srcToChange');
	// A comfortable frame in the middle of the window, not the thumbnail's box.
	var viewW = Math.round(Math.min(860, Math.max(480, window.innerWidth * 0.52)));
	runImageEditor({
		domObj: $cnv_image,
		is_backgroundimage: false,
		src: $cnv_image.attr('src'),
		display_width: viewW,
		display_height: $cnv_image.height(),
		offset_left: 0,
		offset_top: 0
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
