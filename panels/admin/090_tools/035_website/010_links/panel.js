/* global sys */
(function () {
	var setup = {
		info: 'Links',
		route: 'panels.admin.tools.website.links',
		extends: 'panels.admin',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.filterLinks = event => {
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
