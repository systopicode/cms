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

		pagesModule.checkKey = e => {
			if (e.e.originalEvent.keyCode === 13) {
				e.sourceElement.blur();
				return false;
			}
			return true;
		};
	});
})();

function changeSrc(src) {
	$(".srcToChange").attr('src', src);
}

function startCroping() {
	var $cnv_image = $('img.srcToChange');
	runImageEditor({
		domObj: $cnv_image,
		is_backgroundimage: false,
		src: $cnv_image.attr('src'),
		display_width: $cnv_image.width(),
		display_height: $cnv_image.height(),
		offset_left: $cnv_image.offset().left,
		offset_top: $cnv_image.offset().top
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
