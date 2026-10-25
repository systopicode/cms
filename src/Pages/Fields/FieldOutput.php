<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Fields;

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
        if (self::editing()) {
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
            'node_id'      => $page->id,
            'language_iso' => $page->language,
        ]);
        $class = 'editor plaintext';
        $tagLabel = $field->editorLabelText ?? '';
        $value = (string) $field->value;
        switch ($field->tag) {
            case 'input':
                return "<div class='$class' $dataAttrs data-on_input='updateTextfield' data-editorLabel='$tagLabel'><input autocomplete=off name={$field->name} value='$value'/></div>";
            case '':
            case null:
            case 'textarea':
                return "<div class='$class' $dataAttrs data-on_input='updateTextfield'><textarea name={$field->name} value='$value'>$value</textarea></div>";
            default:
                return "<{$field->tag} class='$class' $dataAttrs data-editorLabel='$tagLabel' data-on_input='updatePageParam' contenteditable='true'>$value</{$field->tag}>";
        }
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
        $classes = ['editor'];
        $editableAttributes = \html::arrayToDataAttr([
            'on_input'              => 'updatePageParam',
            'on_drop'               => 'insertMediaInEditor',
            'drop_accept'           => 'media',
            'field'                 => $field->name,
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
            $classes[] = 'mediaDroppable pageDroppable fulltextsearch editor';
        }
        if (!$field->isVisible) {
            $classes[] = 'empty';
        }
        $tagLabel = $field->editorLabelText ?? '';
        return "<div$editableAttributes data-editorLabel='$tagLabel' class='" . implode(' ', $classes) . "'>$fieldContent</div>";
    }
}
