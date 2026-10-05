<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Delete\Panel $this */

switch ($this->action) {
	case 'delete':
		$definition = $this->definition;
		if ($definition === NULL) {
			\message::error('No setting at node ' . (int) \http::get('id') . ' - delete skipped.');
		} elseif ($definition->options !== []) {
			\message::error("The Setting Node {$definition->nodeId} has " . count($definition->options) . ' child(ren) - delete skipped.');
		} else {
			\message::confirm($this->removeDefinition($definition) . ' Junctions removed');
			\message::confirm('1 Setting removed');
			\message::confirm('1 Node removed');
			$this->done = TRUE;
		}
		$this->updateView('lightbox');
		break;
}
