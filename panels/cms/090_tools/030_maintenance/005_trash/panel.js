/* global sys */
(function () {
	var setup = {
		info: 'Trash',
		route: 'panels.cms.tools.maintenance.trash',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.filterTrash = event => {
			var query = String(event.element.value || '').trim().toLowerCase();
			var card = event.element.closest('.widget');
			if (!card) {
				return;
			}
			card.querySelectorAll('tbody tr').forEach(row => {
				row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			});
		};
	});
})();
