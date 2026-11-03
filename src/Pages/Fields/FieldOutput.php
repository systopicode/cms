<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Fields;

use Systopic\Cms\Pages\PageAccess;
use Systopic\Cms\Tables\Pages\TextField;

/**
 * What a text field becomes in the markup — on the site or in the CMS.
 *
 * `<?= $page->text03->tag('h1') ?>` is the same line in a template whether it
 * renders the website or the page preview of the CMS. On the website the field
 * is its content; in the CMS it is the editor for that content. The decision
 * is the one the legacy `pageText` / `pageHtml` made: the CMS is on screen
 * (`PanelTree::inCms()`).
 *
 * The model (`Tables\Pages\TextField`) only holds the value and how it is to be
 * wrapped; the markup of both cases lives here, beside the route — the editor
 * is CMS business, not the row's. The editor markup is the legacy one, field
 * for field: the CMS JavaScript (updatePageParam, updateTextfield,
 * insertMediaInEditor) reads these attributes.
 *
 * A page the user may not change (`PageAccess::mayEdit()`) shows in the CMS
 * as it does on the site.
 */
final class FieldOutput
{
    /** Is the CMS rendering this? Then fields are editors. */
    public static function editing(): bool
    {
        return \Systopic\System\Panels\Tree\PanelTree::getInstance()?->inCms() ?? false;
    }

    public static function text(TextField $field): string
    {
        if (self::editing() && PageAccess::mayEdit($field->page())) {
            return $field->isHtml ? self::htmlEditor($field) : self::textEditor($field);
        }
        return self::live($field);
    }

    // ------------------------------------------------------------------ site

    private static function live(TextField $field): string
    {
        if (!$field->isVisible) {
            return '';
        }
        $tag = $field->tag;
        if ($tag === 'plain') {
            return str_replace(['&lt;', '&gt;'], ['<', '>'], (string) $field->value);
        }
        $content = $field->rendered;
        if ($tag === null || $tag === '') {
            return $content;
        }
        $class = $field->cssClass !== null ? " class='{$field->cssClass}'" : '';
        return "<$tag$class>$content</$tag>";
    }

    // ------------------------------------------------------------------- CMS

    /** pageText::printEditor() */
    private static function textEditor(TextField $field): string
    {
        $page = $field->page();
        $dataAttrs = \html::arrayToDataAttr([
            'field'        => $field->name,
            'field_version' => hash('sha256', (string) $field->value),
            'node_id'      => $page->id,
            'language_iso' => $page->language,
        ]);
        $class = 'editor plaintext';
        $tagLabel = $field->editorLabelText ?? '';
        $fieldName = htmlspecialchars(self::accessibleName($field), ENT_QUOTES, 'UTF-8');
        $value = (string) $field->value;
        switch ($field->tag) {
            case 'input':
                return "<div class='$class' $dataAttrs data-editorLabel='$tagLabel'><input aria-label='$fieldName' data-on_blur='updateTextfield' autocomplete=off name={$field->name} value='$value'/></div>";
            case '':
            case null:
            case 'textarea':
                return "<div class='$class' $dataAttrs><textarea aria-label='$fieldName' data-on_blur='updateTextfield' name={$field->name} value='$value'>$value</textarea></div>";
            default:
                // The field is the block. The chip names that block, unless the
                // template set its own label.
                if ($tagLabel === '') {
                    $tagLabel = self::blockChip((string) $field->tag);
                }
                return "<{$field->tag} class='$class' $dataAttrs data-editorLabel='$tagLabel' aria-label='$fieldName' role='textbox' aria-multiline='true' data-on_blur='updatePageParam' contenteditable='true'>$value</{$field->tag}>";
        }
    }

    private static function accessibleName(TextField $field): string
    {
        $label = trim((string) ($field->editorLabelText ?? ''));
        if ($label !== '') return $label;
        if ($field->name === 'label') return 'Page title';
        if (preg_match('/^(html|text)([0-9]+)$/', $field->name, $match)) {
            return ($match[1] === 'html' ? 'Rich text ' : 'Text ') . (int) $match[2];
        }
        return ucfirst(str_replace('_', ' ', $field->name));
    }

    /** The chip of a plain text field: the same names as a block in a text field. */
    private static function blockChip(string $tag): string
    {
        $tag = strtolower($tag);
        if (preg_match('/^h([1-6])$/', $tag, $match) === 1) {
            return 'Headline H' . $match[1];
        }
        if ($tag === 'p') {
            return 'Paragraph p';
        }
        return $tag;
    }

    /**
     * The html editor: the field's content with its references resolved for
     * the browser (TextField::$editorContent) - images show, the references
     * travel beside them and come back on save (Refs\Resolver::normalize()).
     */
    private static function htmlEditor(TextField $field): string
    {
        $page = $field->page();
        $fieldContent = $field->editorContent;
        $classes = ['editor', 'mediaDroppable', 'pageDroppable', 'fulltextsearch'];
        $editableAttributes = \html::arrayToDataAttr([
            'on_blur'               => 'updatePageParam',
            'on_drop'               => 'insertItemInEditor',
            'drop_accept'           => 'media page',
            'field'                 => $field->name,
            'field_version'         => hash('sha256', (string) $field->value),
            'node_id'               => $page->id,
            'node_original_node_id' => $page->node->relationId('originalNode'),
            'language_iso'          => $page->language,
        ]);
        $editableAttributes .= ' contenteditable="true"';
        if ($fieldContent !== '') {
            // leftovers of the path form, until Tools → Migration has rewritten the field
            if (preg_match_all('~##BEGINMAILTOHREF##(.*?)##ENDMAILTOHREF##~', $fieldContent, $results)) {
                $fieldContent = str_replace($results[0], array_map(static fn(string $address): string => 'mailto:' . $address, $results[1]), $fieldContent);
            }
            $fieldContent = str_replace(
                ['###http::siteRoot###', '###http::varRoot###', '###_IMAGE_DROPPABLE_CLASSNAMES_###', '###_ANCHOR_DROPPABLE_CLASSNAMES_###', '###_AUTO_EMAIL_ANCHOR_CLASSNAMES_###'],
                [\http::$siteRoot, \http::$siteRoot . 'var/', ' mediaDroppable pageDroppable', ' mediaDroppable pageDroppable', 'maillink auto'],
                "\n" . $fieldContent,
            );
        }
        if (!$field->isVisible) {
            $classes[] = 'empty';
        }
        $tagLabel = $field->editorLabelText ?? '';
        $fieldName = htmlspecialchars(self::accessibleName($field), ENT_QUOTES, 'UTF-8');
        return "<div$editableAttributes role='textbox' aria-multiline='true' aria-keyshortcuts='Alt+F10' aria-label='$fieldName' data-editorLabel='$tagLabel' class='" . implode(' ', $classes) . "'>$fieldContent</div>";
    }
}
