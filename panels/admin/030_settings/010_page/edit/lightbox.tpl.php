<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Edit\Panel $this */
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Settings\TemplateEditor;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$d = $this->definition;
$parent = $d?->parent ?? SettingTree::byNodeId($this->scope, (int) \http::get('parent_id'));
$base = $this->parent->href;
$back = $parent ? $base . 'edit/?id=' . $parent->nodeId : $base;
$option = $parent?->type === 'select';
$title = $d?->label ?: ($option ? 'Add option' : ($parent ? 'Add field' : 'Add setting'));
$kind = $d ? TemplateEditor::kind($d) : 'text';
$isValue = $d && ($d->type === 'input' || !$d->type);
?>
<form id="templateSettingForm" class="ajax" method="put" data-feedback="parameters" action="<?= $e($this->href . ($d ? 'saveSetting?id=' . $d->nodeId : 'createSetting?parent_id=' . (int) ($parent?->nodeId ?? 0))) ?>">
<div class="cmslightbox cms pageSettingDialog">
<section>
    <header>
        <h1><?= $e($title) ?></h1>
        <ul class="barmenu"><?php if ($d) { ?><li><button type="button" class="button icononly" aria-label="Delete setting" data-on_click="userConfirm" data-action="removeSetting?id=<?= $d->nodeId ?>" data-confirm="Delete this setting, its fields and all values saved on pages?"><i class="cmsIcon cmsIcon-trash"></i></button></li><?php } ?><li><a class="ajax" href="<?= $e($back) ?>" data-focus-id="template-setting-close" aria-label="Close"><i class="cmsIcon cmsIcon-xmark-large"></i></a></li></ul>
    </header>
    <main>
        <?php if ($parent) { ?><p class="tagsHint">Part of <a class="ajax" href="<?= $e($base . 'edit/?id=' . $parent->nodeId) ?>"><?= $e($parent->label) ?></a></p><?php } ?>

            <div class="fieldGrid is-stack">
                <label class="field"><span><?= $option ? 'Option name' : 'Name shown in Pages' ?></span><input name="label" required value="<?= $e($d?->label ?? '') ?>" placeholder="<?= $option ? 'For example: Center' : 'For example: Show image' ?>" autocomplete="off"></label>
                <?php if (!$d && !$option) { ?>
                    <fieldset class="settingKinds"><legend>What should people be able to do?</legend>
                    <?php foreach (TemplateEditor::KINDS as $key => [$label, $hint]) {
                        if ($parent && !in_array($key, ['text', 'number', 'decimal'], true)) continue;
                    ?><label><input type="radio" name="kind" value="<?= $key ?>" <?= $key === 'text' ? 'checked' : '' ?>><span><b><?= $e($label) ?></b><small><?= $e($hint) ?></small></span></label><?php } ?>
                    </fieldset>
                <?php } elseif ($d && !$option) { ?>
                    <p class="tagsHint">Displayed as: <b><?= $e(TemplateEditor::KINDS[$kind][0]) ?></b></p>
                <?php } ?>
                <?php if ($d) { ?>
                    <label class="field"><span>Description</span><input name="description" value="<?= $e($d->setting->description ?? '') ?>"><small>Describe what this setting controls.</small></label>
                    <?php if ($isValue && !$parent) { ?><label class="field"><span>Default value</span><input name="default" <?= $d->datatype !== 'varchar' ? 'type="number" step="' . ($d->datatype === 'int' ? '1' : 'any') . '"' : '' ?> value="<?= $e($d->default ?? '') ?>"><small>Used when a page has no value of its own.</small></label><?php } ?>
                    <?php if (!$parent) { $templates = (array) \template::getAll(); $picked = array_filter(explode(';', (string) $d->setting->showInTemplates)); ?>
                        <fieldset class="settingTemplates"><legend>Show this setting on</legend>
                            <label><input type="radio" name="visibility" value="all" <?= !$picked ? 'checked' : '' ?>> All templates</label>
                            <label><input type="radio" name="visibility" value="selected" <?= $picked ? 'checked' : '' ?>> Only the templates checked below</label>
                            <div class="templatePick">
                            <?php foreach ($templates as $name => $template) { ?><label><input type="checkbox" name="templates[]" value="<?= $e($name) ?>" <?= in_array((string) $name, $picked, true) ? 'checked' : '' ?>><span><?= $e(trim(str_replace(['_', '-'], ' ', (string) ($template->name ?? $name)))) ?></span></label><?php } ?>
                            </div>
                        </fieldset>
                    <?php } ?>
                    <details><summary>Technical connection</summary><div class="fieldGrid is-stack"><label class="field"><span><?= $option ? 'Value read by the template' : 'Template key' ?></span><input name="name" required pattern="[A-Za-z_][A-Za-z0-9_]*" value="<?= $e($d->name) ?>"><small>Keep this unchanged unless you also update the template code. Changing the visible name does not change this key.</small></label></div></details>
                <?php } else { ?><p class="tagsHint">The technical connection is created automatically. After adding the setting you can configure its options and where it appears.</p><?php } ?>
            </div>
        <?php if ($d && in_array($d->type, ['select', 'group'], true)) { ?>
            <section class="widget pageSettings settingChildren">
                <header><h2><?= $d->type === 'select' ? 'Options' : 'Fields in this group' ?></h2><a class="ajax button" data-focus-id="setting-child-add" href="<?= $e($this->href . '?parent_id=' . $d->nodeId) ?>"><i class="cmsIcon cmsIcon-plus"></i><span><?= $d->type === 'select' ? 'Add option' : 'Add field' ?></span></a></header>
                <?php if (!$d->options) { ?><main><p><?= $d->type === 'select' ? 'Add the options people can choose from.' : 'Add the fields that belong together.' ?></p></main><?php } else { ?>
                <div class="tableScroll"><table><thead><tr><th></th><th>Name in Pages</th><th><?= $d->type === 'select' ? 'Template value' : 'Field type' ?></th><th></th></tr></thead><tbody>
                <?php foreach ($d->options as $child) { ?><tr><td class="pageSettingDrag"><i class="cmsIcon cmsIcon-grip-dots-vertical" aria-label="Drag to reorder" data-drag_type="setting" data-id="<?= $child->nodeId ?>"></i><div class="sort v insertBefore" data-drop_accept="setting" data-on_drop="<?= $e($base . 'insertBefore') ?>" data-id="<?= $child->nodeId ?>"></div><div class="sort v insertAfter" data-drop_accept="setting" data-on_drop="<?= $e($base . 'insertAfter') ?>" data-id="<?= $child->nodeId ?>"></div></td><td><a class="ajax" data-focus-id="setting-child-<?= $child->nodeId ?>" href="<?= $e($this->href . '?id=' . $child->nodeId) ?>"><?= $e($child->label) ?></a></td><td><?= $e($d->type === 'select' ? $child->name : TemplateEditor::KINDS[TemplateEditor::kind($child)][0]) ?></td><td class="icons"><ul><li><a class="ajax button icononly" aria-label="Edit <?= $e($child->label) ?>" href="<?= $e($this->href . '?id=' . $child->nodeId) ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li><li><button type="button" class="button icononly" aria-label="Delete <?= $e($child->label) ?>" data-on_click="userConfirm" data-action="removeChild?id=<?= $d->nodeId ?>" data-child_id="<?= $child->nodeId ?>" data-confirm="Delete this field and its saved page values?"><i class="cmsIcon cmsIcon-trash"></i></button></li></ul></td></tr><?php } ?>
                </tbody></table></div><?php } ?>
            </section>
        <?php } ?>
        <?php if ($d && !$parent) { ?>
            <section class="widget settingPreview"><header><h2>Preview in Pages</h2></header><main>
                <?php if ($d->type === 'checkbox') { ?><label class="checkbox"><span><?= $e($d->label) ?></span><input type="checkbox" class="switch"><div><div></div></div></label>
                <?php } elseif ($d->type === 'select') { ?><label class="field"><span><?= $e($d->label) ?></span><select><option>Choose an option</option><?php foreach ($d->options as $o) { ?><option><?= $e($o->label) ?></option><?php } ?></select></label>
                <?php } elseif ($d->type === 'group') { ?><div class="fieldGrid"><?php foreach ($d->options as $o) { ?><label class="field"><span><?= $e($o->label) ?></span><input <?= $o->datatype !== 'varchar' ? 'type="number"' : '' ?>></label><?php } ?></div>
                <?php } else { ?><label class="field"><span><?= $e($d->label) ?></span><input value="<?= $e($d->default ?? '') ?>"></label><?php } ?>
                <p class="tagsHint">Try the control here. This preview does not change any page.</p>
            </main></section>
        <?php } ?>
    </main>
    <footer><button type="submit" form="templateSettingForm" class="button submit"><i class="cmsIcon cmsIcon-check"></i><span><?= $d ? 'Save changes' : ($option ? 'Add option' : ($parent ? 'Add field' : 'Add setting')) ?></span></button></footer>
</section>
</div>

</form>
