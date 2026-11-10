<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Edit\Panel $this */
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Settings\TemplateEditor;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$d = $this->definition;
$parent = $d?->parent ?? SettingTree::byNodeId($this->scope, (int) \http::get('parent_id'));
$base = $this->parent->href;
$back = $this->backHref($parent);
$option = $parent?->type === 'select';
$newGroup = !$d && !$parent && \http::get('kind') === 'group';
$title = $d?->label ?: ($option ? 'Add option' : ($newGroup ? 'Add group' : ($parent ? 'Add field' : 'Add setting')));
$origin = \http::get('from') === 'list' ? '&from=list' : '';
$kind = $d ? TemplateEditor::kind($d) : 'text';
$isValue = $d && ($d->type === 'input' || !$d->type);
?>
<div class="cmslightbox cms pageSettingDialog">
<section>
    <header>
        <h1><?= $e($title) ?></h1>
        <ul class="barmenu"><?php if ($d) { ?><li><button type="button" class="button icononly" aria-label="Delete setting" data-on_click="userConfirm" data-action="removeSetting?id=<?= $d->nodeId ?><?= $e($origin) ?>" data-confirm="Delete this setting, its fields and all values saved on pages?"><i class="cmsIcon cmsIcon-trash"></i></button></li><?php } ?><li><a class="ajax" href="<?= $e($back) ?>" data-focus-id="template-setting-close" aria-label="Close"><i class="cmsIcon cmsIcon-xmark-large"></i></a></li></ul>
    </header>
    <main>
<form id="templateSettingForm" class="ajax" method="put" data-feedback="parameters" action="<?= $e($this->href . ($d ? 'saveSetting?id=' . $d->nodeId . $origin : 'createSetting?parent_id=' . (int) ($parent?->nodeId ?? 0))) ?>">
        <?php if ($parent) { ?><p class="tagsHint">Part of <a class="ajax" href="<?= $e($base . 'edit/?id=' . $parent->nodeId) ?>"><?= $e($parent->label) ?></a></p><?php } ?>

            <?php if ($d) { ?><section class="widget is-list"><header><h2><?= $option ? 'Option' : ($d->type === 'group' ? 'Group' : 'Setting') ?></h2></header><main><?php } ?>
            <div class="fieldGrid is-stack">
                <label class="field"><span><?= 'Label' ?></span><input name="label" required value="<?= $e($d?->label ?? '') ?>" placeholder="<?= $option ? 'For example: Center' : 'For example: Show image' ?>" autocomplete="off"></label>
                <?php if ($newGroup) { ?><input type="hidden" name="kind" value="group"><?php } elseif (!$d && !$option) { ?>
                    <fieldset class="settingKinds choiceCards" data-feedback="none"><legend>What should people be able to do?</legend>
                    <?php foreach (TemplateEditor::KINDS as $key => [$label, $hint]) {
                        if (in_array($key, ['number', 'group'], true)) continue;
                    ?><label><input type="radio" name="kind" value="<?= $key ?>" <?= $key === 'text' ? 'checked' : '' ?>><span><b><?= $e($label) ?></b><small><?= $e($hint) ?></small></span></label><?php } ?>
                    </fieldset>
                <?php } elseif ($d && !$option) { ?>
                    <div class="field"><span>Type</span><div><?= $e(TemplateEditor::KINDS[$kind][0]) ?></div></div>
                <?php } ?>
                <?php if ($d) { ?>
                    <?php if ($option) { ?>
                        <label class="field"><span>Key</span><input name="name" required pattern="[A-Za-z_][A-Za-z0-9_]*" title="Use letters, digits and underscores. Start with a letter or underscore." value="<?= $e($d->name) ?>"></label>
                    <?php } else { ?>
                    <label class="field"><span>Key</span><input name="name" value="<?= $e($d->name) ?>" readonly><small>The key your templates use. It stays the same if you change the label.</small></label>
                    <label class="field"><span>Description <small>(optional)</small></span><input type="text" name="description" value="<?= $e(preg_replace('/\R/u', ' ', $d->setting->description ?? '')) ?>"><small>Describe what this setting controls.</small></label>
                    <?php if ($isValue && !$parent) { ?><label class="field"><span>Default value</span><input name="default" <?= $d->datatype !== 'varchar' ? 'type="number" step="' . ($d->datatype === 'int' ? '1' : 'any') . '"' : '' ?> value="<?= $e($d->default ?? '') ?>"><small>Used when a page has no value of its own.</small></label><?php } ?>
                    <?php } ?>
                <?php } else { ?><p class="tagsHint">After adding the setting you can configure its options and where it appears.</p><?php } ?>
            </div>
        <?php if ($d) { ?></main></section><?php } ?>
                    <?php if ($d && !$parent) { $templates = (array) \template::getAll(); $picked = array_filter(explode(';', (string) $d->setting->showInTemplates)); $allTemplates = !$picked; ?>
                        <section class="widget is-list"><header><h2>Template visibility</h2></header><main><div class="settingTemplates">
                            <div class="choiceCards" role="group" aria-label="Visible in" data-feedback="none">
                                <label><input type="radio" name="visibility" value="all" <?= $allTemplates ? 'checked' : '' ?>><span><b>All templates</b><small>Show this setting in every template.</small></span></label>
                                <label><input type="radio" name="visibility" value="selected" <?= !$allTemplates ? 'checked' : '' ?>><span><b>Selected templates</b><small>Choose the templates below.</small></span></label>
                            </div>
                            <div class="templatePick">
                            <?php foreach ($templates as $name => $template) { $choiceId="setting-template-" . substr(sha1((string)$name),0,12); ?><div class="settingChoice"><input class="custom" id="<?= $choiceId ?>" type="checkbox" name="templates[]" data-feedback="none" value="<?= $e($name) ?>" <?= in_array((string) $name, $picked, true) ? 'checked' : '' ?>><label for="<?= $choiceId ?>" aria-hidden="true"></label><label for="<?= $choiceId ?>"><?= $e(trim(str_replace(['_', '-'], ' ', (string) ($template->name ?? $name)))) ?></label></div><?php } ?>
                            </div>
                        </div></main></section>
                    <?php } ?>
        <?php if ($d && in_array($d->type, ['select', 'group'], true)) { ?>
            <section class="widget is-list pageSettings settingChildren">
                <header><h2><?= $d->type === 'select' ? 'Options' : 'Fields in this group' ?></h2><a class="ajax button" data-focus-id="setting-child-add" href="<?= $e($this->href . '?parent_id=' . $d->nodeId) ?>"><i class="cmsIcon cmsIcon-plus"></i><span><?= $d->type === 'select' ? 'Add option' : 'Add field' ?></span></a></header>
                <?php if (!$d->options) { ?><main><p><?= $d->type === 'select' ? 'Add the options people can choose from.' : 'Add the fields that belong together.' ?></p></main><?php } else { ?>
                <div class="tableScroll"><table><thead><tr><th></th><th>Label</th><th><?= $d->type === 'select' ? 'Template value' : 'Field type' ?></th><th></th></tr></thead><tbody>
                <?php foreach ($d->options as $child) { ?><tr><td class="pageSettingDrag"><i class="cmsIcon cmsIcon-grip-dots-vertical" aria-label="Drag to reorder" data-drag_type="setting" data-id="<?= $child->nodeId ?>"></i><div class="sort v insertBefore" data-drop_accept="setting" data-on_drop="<?= $e($this->href . 'reorderChild?id=' . $d->nodeId) ?>" data-position="before" data-id="<?= $child->nodeId ?>"></div><div class="sort v insertAfter" data-drop_accept="setting" data-on_drop="<?= $e($this->href . 'reorderChild?id=' . $d->nodeId) ?>" data-position="after" data-id="<?= $child->nodeId ?>"></div></td><td><?= $e($child->label) ?></td><td><?= $e($d->type === 'select' ? $child->name : TemplateEditor::KINDS[TemplateEditor::kind($child)][0]) ?></td><td class="icons"><ul><li><a class="ajax button icononly" aria-label="Edit <?= $e($child->label) ?>" data-focus-id="setting-child-edit-<?= $child->nodeId ?>" href="<?= $e($this->href . '?id=' . $child->nodeId) ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li><li><button type="button" class="button icononly" aria-label="Delete <?= $e($child->label) ?>" data-on_click="userConfirm" data-action="removeChild?id=<?= $d->nodeId ?>" data-child_id="<?= $child->nodeId ?>" data-confirm="Delete this field and its saved page values?"><i class="cmsIcon cmsIcon-trash"></i></button></li></ul></td></tr><?php } ?>
                </tbody></table></div><?php } ?>
            </section>
        <?php } ?>
</form>
        <?php if ($d && !$parent) { ?>
            <aside class="widget is-list settingPreview" data-feedback="none" aria-label="Preview in Pages"><header><h2>Preview in Pages</h2></header><main><p class="tagsHint">Preview only — try how this field will work in Pages. Changes here are not saved.</p><div class="settingPreviewControl">
                <?php
                $preview = static function($field) use (&$preview, $e): void {
                    if ($field->type === 'group') {
                        echo '<div class="fieldGrid is-stack">';
                        foreach ($field->options as $child) $preview($child);
                        echo '</div>';
                    } elseif ($field->type === 'checkbox') {
                        echo '<label class="checkbox"><span>'.$e($field->label).'</span><input type="checkbox" class="switch"><div><div></div></div></label>';
                    } elseif ($field->type === 'select') {
                        echo '<label class="field"><span>'.$e($field->label).'</span><select><option>Choose an option</option>';
                        foreach ($field->options as $option) echo '<option>'.$e($option->label).'</option>';
                        echo '</select></label>';
                    } else {
                        echo '<label class="field"><span>'.$e($field->label).'</span><input '.($field->datatype !== 'varchar' ? 'type="number" step="any"' : '').' value="'.$e($field->default ?? '').'"></label>';
                    }
                };
                $preview($d);
                ?>
                </div>
            </main></aside>
        <?php } ?>
    </main>
    <footer><button type="submit" form="templateSettingForm" class="button submit"><i class="cmsIcon cmsIcon-check"></i><span><?= $d ? 'Save changes' : ($option ? 'Add option' : ($newGroup ? 'Add group' : ($parent ? 'Add field' : 'Add setting'))) ?></span></button></footer>
</section>
</div>
