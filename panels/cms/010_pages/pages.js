sys.onDocumentReady(function () {
    scrollTree();
    if (new URLSearchParams(location.search).get('relations') === 'aliases') focusPageRelations('aliases');
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
    revealRequestedPageRelations();
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
	var itemRect = (activeElement.querySelector(':scope > label') || activeElement).getBoundingClientRect();
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
        if (!magick && sys.core.server?.revealScroll) sys.core.server.revealScroll(scroller, top);
        else scroller.scrollTop = top;
	}
}

function focusPageRelations(part = '') {
    // Let the updated panel and its flex layout settle before measuring.
    requestAnimationFrame(function () { requestAnimationFrame(function () {
        var editor = document.querySelector('main > .content');
        if (editor) editor.dataset.cmsProperties = 'true';
        var section = document.querySelector('aside.cms details[data-section="relations"]');
        if (!section) return;
        section.open = true;
        var target = part === 'aliases' ? section.querySelector('[data-relation-section="aliases"]') || section : section;
        var scroller = section.closest('.scrollContainer');
        if (scroller) {
            var top = scroller.scrollTop + target.getBoundingClientRect().top - scroller.getBoundingClientRect().top - 8;
            if (sys.core.server?.revealScroll) sys.core.server.revealScroll(scroller, top);
            else scroller.scrollTop = top;
        }
        section.querySelector('summary')?.focus({preventScroll: true});
        scrollTree('instant', false, false);
    }); });
}


// Ignore late search answers after further typing, a page change or leaving Pages.
// Use the existing command handler to initialise result actions consistently.
function updatePageSearchResults(term, html) {
    var field = document.querySelector('#cmsSearchInput');
    if (!field || field.value.trim() !== term) return;
    new sys.core.server.command({response: {command: 'replaceInner', selector: 'ul#cmsSearchResults', html: html}});
}
function updatePageRelationResults(source, kind, term, html) {
    var selector = '.scrollContainerContent[data-node_id="' + Number(source) + '"] .relationSearch[data-relation="' + CSS.escape(kind) + '"]';
    var field = document.querySelector(selector + ' input');
    if (!field || field.value.trim() !== term) return;
    new sys.core.server.command({response: {command: 'replaceInner', selector: selector + ' .relationResults', html: html}});
}

function revealRequestedPageRelations() {
    var params = new URLSearchParams(location.search);
    if (params.get('relations') === 'aliases') focusPageRelations('aliases');
    if (params.get('reveal') === 'tree' || params.get('relations') === 'aliases') {
        requestAnimationFrame(function () { requestAnimationFrame(function () {
            scrollTree('instant', false, false);
        }); });
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', revealRequestedPageRelations, {once: true});
} else {
    revealRequestedPageRelations();
}
document.addEventListener('cms:rendered', revealRequestedPageRelations);

function clearPageRelationSearch(source) {
    var root = document.querySelector('.scrollContainerContent[data-node_id="' + Number(source) + '"]');
    if (!root) return;
    root.querySelectorAll('.relationSearch input').forEach(function (field) { field.value = ''; });
    root.querySelectorAll('.relationResults').forEach(function (list) { list.replaceChildren(); });
}
