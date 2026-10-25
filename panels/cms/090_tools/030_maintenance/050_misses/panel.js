/* global sys */
(function () {
	var setup = {
		info: 'Unknown addresses',
		route: 'panels.cms.tools.maintenance.misses',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.filterMisses = event => {
			var query = String(event.element.value || '').trim().toLowerCase();
			var card = event.element.closest('.widget');
			if (!card) {
				return;
			}
			card.querySelectorAll('tbody tr').forEach(row => {
				row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			});
		};
		// The chosen page is a select, and a click only sends data-* attributes.
		// The copy of those attributes is already on the event, so the page id
		// goes there before the action runs.
		this.redirectMiss = event => {
			var row = event.element.closest('tr');
			var select = row && row.querySelector('select');
			if (!select || !select.value) {
				return;
			}
			var page = select.options[select.selectedIndex].text;
			if (!confirm('Send this address to “' + page + '”?')) {
				return;
			}
			event.data.dataset.node_id = select.value;
			event.executeAction('redirect');
		};
	});
})();
