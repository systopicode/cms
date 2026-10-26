sys.onDocumentReady(function () {
	scrollTree();
});

// While the field has a word, its hits stand in for the tree.
document.addEventListener('input', function (event) {
	var field = event.target;
	if (!field || field.id !== 'cmsSearchInput') {
		return;
	}
	var tree = field.closest('nav') && field.closest('nav').querySelector('div.tree');
	if (tree) {
		tree.classList.toggle('is-results', field.value.trim() !== '');
	}
});

function onAfterHisotryPush(url) {

}

/**
 * Bring a tree row into the pylon scroller.
 *
 * magick: only if it is outside the visible area, and then only as far as
 * needed. Without it the row is aligned to the top — that is the crosshairs
 * button. scrollIntoView is not used: it would also scroll the page.
 */
function scrollTree(running = 'instant', id = false, magick = true) {
	var activeElement = id
		? document.querySelector('.cms ul.tree li[data-node_id="' + id + '"]')
		: document.querySelector('.cms ul.tree li.active');
	if (!activeElement) {
		return;
	}
	var scroller = activeElement.closest('div.tree');
	if (!scroller) {
		return;
	}
	var itemRect = activeElement.getBoundingClientRect();
	var box = scroller.getBoundingClientRect();
	var above = itemRect.top < box.top;
	var below = itemRect.bottom > box.bottom;
	if (magick && !above && !below) {
		return;
	}
	var delta = magick
		? (above ? itemRect.top - box.top : itemRect.bottom - box.bottom)
		: itemRect.top - box.top - 4;
	var top = scroller.scrollTop + delta;
	if (running === 'smooth' && scroller.scrollTo) {
		scroller.scrollTo({top: top, behavior: 'smooth'});
	} else {
		scroller.scrollTop = top;
	}
}
