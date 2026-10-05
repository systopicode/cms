sys.onDocumentReady(function () {
	scrollTree();
});

function onAfterHisotryPush(url) {

}

function scrollTree(running = 'instant', id = false, magick = true) {
	if (id) {
		var activeElement = document.querySelectorAll('.cms ul.tree li[data-node_id="' + id + '"]')[0];
	} else {
		var activeElement = document.querySelectorAll('.cms ul.tree li.active')[0];
	}

	if (typeof activeElement !== 'undefined') {
		if (magick) {
			var tree = document.querySelectorAll('.cms ul.tree')[0];
			if (activeElement.offsetTop > tree.offsetHeight) {
				activeElement.scrollIntoView({behavior: running, block: "start", inline: "end"});
			}
		} else {
			activeElement.scrollIntoView({behavior: running, block: "start", inline: "end"});
		}

}
}
