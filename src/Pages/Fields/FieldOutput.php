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

    /** pageHtml::printEditor() — internal links, protected mail addresses, placeholders. */
    private static function htmlEditor(TextField $field): string
    {
        global $user;
        $page = $field->page();
        $fieldContent = (string) $field->value;
        $classes = ['editor'];
        $autoEmailAnchorClassnames = 'maillink auto';

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
        $droppableClassnames = ' mediaDroppable pageDroppable';

        if ($fieldContent !== '') {
            // internal links
            if (preg_match_all('~###http::siteRoot###.*?\?id=(\d+)((\&amp;lang=([a-z]+))|())~i', $fieldContent, $results)) {
                $replacements = [];
                $matches = [];
                foreach ($results[0] as $key => $result) {
                    $linkId = $results[1][$key];
                    $linkLang = empty($results[4][$key]) ? $page->language : $results[4][$key];
                    $link = getLinkpath($linkId, $linkLang);
                    if (!empty($link)) {
                        $matches[] = $result;
                        $replacements[] = "###http::siteRoot###$link";
                    }
                }
                if (!checkAccess($user, 'updatePageParam')) { // pre replacements when no editor is logged in
                    $fieldContent = str_replace($matches, $replacements, $fieldContent);
                }
            }
            // mail links: the marker becomes a plain mailto:
            //
            // The legacy offered to hide the address behind an ajax round trip
            // (`html_output.protect_email_addresses`, a row in tmp_data, the
            // `getlink` action in site/actions.php). None of it worked: the
            // setting is defined nowhere, the table is tmp_datas, the action
            // reads a column that does not exist, and `clearTempData()` — called
            // before every replacement — is no function, so a field with a
            // marker ended the request. Dropped 2026-10-02; mailto is what the
            // branch that ran would have produced.
            if (preg_match_all('~##BEGINMAILTOHREF##(.*?)##ENDMAILTOHREF##~', $fieldContent, $results)) {
                $replacements = array_map(static fn(string $address): string => 'mailto:' . $address, $results[1]);
                $fieldContent = str_replace($results[0], $replacements, $fieldContent);
            }
            $fieldContent = "\n" . str_replace([
                '###http::siteRoot###',
                '###http::varRoot###',
                '###_IMAGE_DROPPABLE_CLASSNAMES_###',
                '###_ANCHOR_DROPPABLE_CLASSNAMES_###',
                '###_AUTO_EMAIL_ANCHOR_CLASSNAMES_###',
            ], [
                \http::$siteRoot,
                \http::$siteRoot . 'var/',
                $droppableClassnames,
                $droppableClassnames,
                $autoEmailAnchorClassnames,
            ], $fieldContent);
            $classes[] = 'mediaDroppable pageDroppable fulltextsearch editor';
        }
        if (!$field->isVisible) {
            $classes[] = 'empty';
        }
        $tagLabel = $field->editorLabelText ?? '';
        return "<div$editableAttributes data-editorLabel='$tagLabel' class='" . implode(' ', $classes) . "'>$fieldContent</div>";
    }
}
