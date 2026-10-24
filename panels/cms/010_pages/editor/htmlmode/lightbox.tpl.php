<?php
/**
 * The HTML lightbox: one field of the page as raw HTML.
 *
 * @var \Systopic\System\Panels\PanelNode $this  cms/pages/editor/htmlmode
 */

use Systopic\Db\Type\Text;
use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Cms\Pages\Fields\HtmlSource;

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent->parent;
$nodeId = (int) http::get('id');
$field = (string) http::get('field');
$property = PageOperator::propertyOf($field);
$row = $property === null ? null : $pages->pageFor($nodeId)?->row;
$value = $row === null ? '' : $row->$property;
$value = $value instanceof Text ? $value->value : (string) $value;
?>
<form method="post" action="../../updateHTML">
    <input name='field' value='<?= htmlspecialchars($field, ENT_QUOTES) ?>' type='hidden'>
    <input name='node_id' value='<?= $nodeId ?>' type='hidden'>
    <div class="cmslightbox cms" data-size='3'>
        <section>
            <header>
                <h1>HTML</h1>
                <ul class="barmenu">
                    <li>
                        <a class="ajax" href="../../" title="Close"><i class="fa-regular fa-xmark"></i></a>
                    </li>
                </ul>
            </header>
            <main>
                <textarea class="htmlSource" spellcheck="false" autocapitalize="off" autocomplete="off" name="html" autofocus><?= htmlspecialchars(HtmlSource::pretty($value)) ?></textarea>
            </main>
            <footer>
                <button type="submit" onclick="this.classList.add('loading')" class="submit">Save</button>
            </footer>
        </section>
    </div>
</form>
