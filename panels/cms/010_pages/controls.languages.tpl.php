<?php
/**
 * Shown instead of the preview when the node has no page in this language:
 * create it empty, or from a language it exists in.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
$page = $this->page;
if ($page === null) {
	return;
}
echo "<div class=cms style='margin:20px;'>";
echo "<a class='button submit ajax put' href='newLang' data-node_id='{$page->id}' >";
echo "Create empty Page";
echo "</a>";
foreach ($page->languages as $languageIso) {
	echo "<a class='button submit ajax put' href='newLang' data-node_id='{$page->id}' data-source_lang='$languageIso' >";
	echo "Create from $languageIso";
	echo "</a>";
}
echo "</div>";
