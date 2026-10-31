sys.onDocumentReady(function () {
    scrollTree('smooth');
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

var treeRequestedId = new URLSearchParams(location.search).get('id');
function onAfterHisotryPush(url) {
    revealRequestedPageRelations();
    var id = new URLSearchParams(location.search).get('id');
    if (id !== treeRequestedId) {
        requestAnimationFrame(() => requestAnimationFrame(() => scrollTree('smooth', id || false, true)));
    }
    treeRequestedId = id;
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
	var top = Math.max(0, Math.min(scroller.scrollHeight - scroller.clientHeight, scroller.scrollTop + delta));
    if (Math.abs(top - scroller.scrollTop) < 2) return;
    scrollTree.targets ||= new WeakMap();
    const previousTarget = scrollTree.targets.get(scroller);
    if (previousTarget && Math.abs(previousTarget.top - top) < 2 && Date.now() - previousTarget.time < 600) return;
    scrollTree.targets.set(scroller, {top, time:Date.now()});
    if (sys.core.server?.revealScroll) sys.core.server.revealScroll(scroller, top, running);
    else if (running === 'smooth' && scroller.scrollTo && !matchMedia('(prefers-reduced-motion: reduce)').matches) scroller.scrollTo({top, behavior: 'smooth'});
    else scroller.scrollTop = top;
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
        scrollTree('smooth', false, false);
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
            scrollTree('smooth', false, false);
        }); });
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', revealRequestedPageRelations, {once: true});
} else {
    revealRequestedPageRelations();
}


function clearPageRelationSearch(source) {
    var root = document.querySelector('.scrollContainerContent[data-node_id="' + Number(source) + '"]');
    if (!root) return;
    root.querySelectorAll('.relationSearch input').forEach(function (field) { field.value = ''; });
    root.querySelectorAll('.relationResults').forEach(function (list) { list.replaceChildren(); });
}

// Optimistic field versions: a conflict never silently replaces either edit.
function pageVersionFields(node, lang, field) {
    return [...document.querySelectorAll('[data-field]')].filter(el => el.dataset.field === field && String(el.dataset.node_id) === String(node) && String(el.dataset.language_iso || '') === String(lang || ''));
}
function pageEditorOwnSave(node, lang) {
    (window.cmsOwnPageSaves ||= new Set()).add(String(node) + ':' + String(lang || ''));
}

function pageEditorVersion(node, lang, field, version, saved = false) {
    if (saved) (window.cmsOwnPageSaves ||= new Set()).add(String(node) + ':' + String(lang || ''));
    pageVersionFields(node, lang, field).forEach(el => { el.dataset.field_version = version; const hidden = el.closest('form')?.querySelector('[name=field_version]'); if (hidden) hidden.value = version; });
}
function pageEditConflict(node, lang, field, version, metadata = {}) {
    // A delayed save response belongs to the field that submitted it, not the next page.
    if (!pageVersionFields(node, lang, field).length) return;
    document.getElementById('pageEditConflict')?.remove();
    const box = document.createElement('div');
    box.id = 'pageEditConflict'; box.className = 'cmslightbox cms pageConflict';
    box.innerHTML = '<section><header><h1>Review changes</h1><button type="button" class="button icononly" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></header><main><p>The saved value of this field differs from the version you opened. Your changes are still here.</p><p>Load the latest page to review their changes, or explicitly overwrite this field with yours.</p></main><footer><button type="button" class="button" data-choice="load"><i class="cmsIcon cmsIcon-arrows-rotate"></i>Load latest page</button><button type="button" class="button highlight" data-choice="keep"><i class="cmsIcon cmsIcon-check"></i>Overwrite anyway</button></footer></section>';
    const info = document.createElement('p');
    info.className = 'tagsHint';
    info.textContent = metadata.at || metadata.author
        ? 'Latest page update' + (metadata.author ? ' by ' + metadata.author : '') + (metadata.at ? ' · ' + metadata.at + ' UTC' : '') + '. Field history is not recorded.'
        : 'Author and time are not available for this change.';
    box.querySelector('main').append(info);
    box.querySelector('[aria-label="Close"]').onclick = () => box.remove();
    box.querySelector('[data-choice="load"]').onclick = () => {
        if (confirm('Reload this page? Unsaved changes on this page will be discarded.')) {
            document.querySelectorAll('[data-save-state]').forEach(el => window.cmsFeedback?.cancel?.(el));
            location.reload();
        }
    };
    box.querySelector('[data-choice="keep"]').onclick = () => {
        const candidates = pageVersionFields(node, lang, field);
        const el = candidates.find(el=>el.closest('.cmslightbox')) || candidates[0];
        if (!el) { box.remove(); return; }
        // The exact conflicting version is acknowledged. A third edit will
        // conflict again rather than being overwritten by an unrestricted force flag.
        pageEditorVersion(node, lang, field, version);
        const input = el.matches('input,textarea,select,[contenteditable]') ? el : el.querySelector('input,textarea,select');
        box.remove();
        if (input?.dataset.conflictSubmit) input.closest('form').requestSubmit();
        else if (input?.dataset.on_change) input.dispatchEvent(new Event('change',{bubbles:true}));
        else if (input) { input.focus(); input.blur(); }
    };
    document.body.append(box);
}


// Advisory only: works across devices without a hub and never locks a viewer out.
(function () {
    if (window.cmsEditorPresence) return;
    window.cmsEditorPresence = true;
    // Retire the previous document on reload; a new tab keeps its own identity.
    let previousClient;
    try {
        if (performance.getEntriesByType('navigation')[0]?.type === 'reload') previousClient = sessionStorage.getItem('cmsEditorPresenceClient');
    } catch {}
    const client = crypto.randomUUID?.() || Array.from(crypto.getRandomValues(new Uint8Array(16)), n=>n.toString(16).padStart(2,'0')).join('');
    try { sessionStorage.setItem('cmsEditorPresenceClient', client); } catch {}
    const revisions = new Map();
    let pending = false, pendingAt = 0, last = 0, lastKey = '', active = null, generation = 0, closing = null;
    function check() {
        const notice = document.querySelector('[data-editor-presence]');
        if (!window.sys?.core?.server?.call || !window.sys?.http?.parseURL) return;
        if (!notice) {
            if (active) {
                const old=active;active=null;lastKey='';
                const leave=new sys.core.server.call({method:'put',id:old.node,lang:old.lang,client,leave:1});
                leave.background=true;leave.send(old.endpoint);
            }
            return;
        }
        if (pending && Date.now() - pendingAt < 10000) return;
        active={node:notice.dataset.node,lang:notice.dataset.lang,endpoint:notice.dataset.endpoint};
        const key = notice.dataset.node + ':' + notice.dataset.lang;
        if (key === lastKey && Date.now() - last < 1000) return;
        const request = ++generation;
        last = Date.now(); lastKey = key; pending = true; pendingAt = Date.now();
        const call = new sys.core.server.call({method:'put', id:notice.dataset.node, lang:notice.dataset.lang, client, previousClient});
        call.background = true;
        call.send(notice.dataset.endpoint).done(data => {
            if (request !== generation) return;
            pending = false;
            const current = document.querySelector('[data-editor-presence]');
            if (!notice.isConnected || current !== notice || key !== current.dataset.node + ':' + current.dataset.lang) { last = 0; queueMicrotask(check); return; }
            if (!data?.available) return;
            const state = revisions.get(key) || {};
            const changed = state.revision && state.revision !== data.revision;
            state.revision = data.revision;
            revisions.set(key,state);
            // A live render already refreshed untouched fields. Retained edits still
            // use their original field version and will conflict when necessary.
            const own = changed && window.cmsOwnPageSaves?.delete(key);
            if (changed && !own) state.updatedAt = Date.now();
            const updated = Date.now() - (state.updatedAt || 0) < 8000;
            if (data.others > 0 || updated) {
                closing?.cancel(); closing = null;
                const label = updated ? 'This page was updated. Reload to review the latest version; unsaved changes are protected.' : (data.names?.length ? 'Also open: ' + data.names.join(', ') + '. Your changes are checked when saving.' : 'Also open in another session. Your changes are checked when saving.');
                if (!notice.hidden && notice.querySelector('span')?.textContent === label) return;
                notice.replaceChildren();
                const icon = document.createElement('i'); icon.className='cmsIcon cmsIcon-circle-info';
                const text = document.createElement('span');
                text.textContent = label;
                notice.append(icon,text);
                notice.hidden=false;
                state.label=label;
            } else if (!notice.hidden) {
                const height=notice.getBoundingClientRect().height;
                closing?.cancel();
                closing = notice.animate([{height:height+'px',opacity:1,paddingTop:'.45rem',paddingBottom:'.45rem'},{height:'0px',opacity:0,paddingTop:0,paddingBottom:0}],{duration:240,easing:'ease-in-out'});
                const animation = closing;
                animation.finished.then(()=>{if(closing !== animation) return; notice.hidden=true;notice.replaceChildren();delete state.label;closing=null;}).catch(()=>{});
            }
        }).fail(() => { if (request === generation) pending=false; });
    }
    document.addEventListener('cms:rendered', () => {
        const notice=document.querySelector('[data-editor-presence]');
        const label=notice && revisions.get(notice.dataset.node+':'+notice.dataset.lang)?.label;
        if(label && notice.hidden){
            const icon=document.createElement('i');icon.className='cmsIcon cmsIcon-circle-info';
            const text=document.createElement('span');text.textContent=label;
            notice.replaceChildren(icon,text);notice.hidden=false;
        }
        last=0;check();
    });
    // Closing/reloading the document must not leave a 45-second ghost heartbeat.
    window.addEventListener('pagehide', () => {
        if (!active) return;
        fetch(active.endpoint, {method:'PUT',credentials:'same-origin',keepalive:true,
            headers:{...sys.core.server.headersFor?.('put'),'X-Requested-With':'XMLHttpRequest','Content-Type':'application/json'},
            body:JSON.stringify({method:'put',id:active.node,lang:active.lang,client,leave:1})
        }).catch(()=>{});
    });
    function startPresence() {
    new MutationObserver(() => {
        const marker=document.querySelector('[data-editor-presence]');
        const key=marker?marker.dataset.node+':'+marker.dataset.lang:'';
        if (key!==lastKey) requestAnimationFrame(check);
    }).observe(document.body,{childList:true,subtree:true});
    document.addEventListener('visibilitychange',check);
    setInterval(check,2000);
    check();
    }
    if (document.body) startPresence();
    else document.addEventListener('DOMContentLoaded',startPresence,{once:true});
})();

// A remembered selection has no ?id= on entry. Reveal it once after the AJAX
// render; remote refreshes of the same selection retain their scroll position.
(function(){
    let selected='';
    document.addEventListener('cms:rendered',()=>{
        const row=document.querySelector('.cms ul.tree li.active');
        const id=row?.dataset.node_id || '';
        if(id && id!==selected) requestAnimationFrame(()=>scrollTree('smooth',id,true));
        selected=id;
    });
})();
