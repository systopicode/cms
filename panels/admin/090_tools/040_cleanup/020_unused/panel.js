/* global sys */
(function () {
	var setup = {
		info: 'Unused media',
		route: 'panels.admin.tools.cleanup.unused',
		extends: 'panels.admin',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.filterUnused = event => {
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
