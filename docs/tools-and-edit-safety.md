# Tools and editing safety

Tools navigation groups existing panels; it does not rename their routes or reset user state.
System, website checks, cleanup, logs, database, remote debugging, media repair and migration
remain explicit destinations. Website checks share navigation tabs; each retains its actions.
Panels absent from an installation are omitted. Permissions still come from the original panel tree.

The media editor's **Used on** accordion finds library placements (including media aliases),
content references, legacy file paths and share images. Results include language and usage type.
Delete confirmations in the editor and library show the number of page references. References
from custom PHP templates or external websites cannot be discovered by this database scan.

Inline page text and HTML editors carry a SHA-256 version of their stored field. Saving compares
against the actual database row under a row lock, inside the same UoW transaction as the write.
A conflicting edit remains unsaved. **Overwrite anyway** acknowledges that exact competing
version; another intervening edit triggers a new conflict. **Load latest page** reloads after
confirming that local unsaved changes will be discarded. Closing the dialog retains local edits.
Raw HTML editing uses the same check. Existing legacy callers without a version retain their
previous behavior; this is field-content protection, not a global lock on all system settings.
The focus-preserving renderer keeps the version belonging to the retained editor content.
UoW notifications still run after commit, so live subscribers cannot read a pre-commit state.

Failed blur autosaves offer Retry in the existing status indicator. Retry reads the current input
and invokes its own save event. Destructive requests, uploads and email sends are not replayed:
they keep their own action-specific controls to avoid duplicate side effects.

Checks: media-usage.php, page-edit-conflict.php, page-edit-conflict-ui.mjs (cms);
uow-write-guard.php, gui-feedback-ui.mjs, pages-blur-save-ui.mjs (system).
For a real two-browser check, edit the same content field in two browsers, save the first and
then the second. Verify conflict, cancellation, explicit overwrite and a third competing edit.

Logs → Failed actions lists the latest 20 failed requests in the current browser runtime.
It records only time, generic result and a sanitized page URL (id/lang/target_id); no field
contents, response bodies or secrets. A full browser reload clears this transient list.
Server-side mail/PHP logs retain their existing role. Opening a listed page still respects
the unsaved-changes navigation guard.

### Advisory editor presence

Opening the same content in another tab/device shows an inline advisory, not a lock or an approval dialog. A lightweight request runs every five seconds while a Pages view is open, independent of Centrifugo. It detects page revision changes and advises reloading; retained edits still use the existing field-version conflict check. Aliases share the underlying page presence key.

The project-local `var/tmp/page-editors.json` contains only page/language keys, hashed session/tab identifiers and timestamps. Entries expire after 45 seconds and are pruned on the next check. The optional advisory fails quietly when temporary storage is unavailable; transactional conflict protection remains active. There is no permanent user setting and no additional external service.

Media editor and replacement use the same usage list template and sidebar fold markup. Website checks use only the existing Tools navigation, without another tab row.

Sidebar Meta/Publishing fields and template settings now carry per-field versions too. Junction changes lock the page node and compare only the relevant setting; unrelated settings do not conflict. The presence marker is a fixed-height flex item outside article.cmsContent. Normal CMS navigation releases it immediately; closed tabs expire by TTL.

## Media adjustments and GUI follow-up

Relative image controls are composed with the saved format tune. Applying colour no longer drops rotation/reflection, and a transform preserves saved colour controls. Reflecting an already rotated image correctly reverses the rotation axis. Reset-to-original continues to remove the format's saved tune explicitly. The original file remains untouched.

File preview frames show the shared spinner while image/video/audio/PDF content loads. The image-format selector is marked as a presentation control and does not receive a save outline. Used-on cards reuse incoming-relation rows without an inset border. Alias/redirect title actions are native transparent buttons and work with keyboard activation.

Tree reveal clamps its target to the available scroll range, skips negligible/duplicate requests and reveals remembered selections after an AJAX entry without `?id=`. A live refresh with an unchanged selection does not trigger this entry reveal.

The self-contained SketchUp ES module lives under `public/modules/` and is imported only by the 3D viewer. It must not be put back under the globally scanned `public/js/` directory as a classic script.

## Publishing and branch previews (1 November 2026)

`Pages\PublishingState` is shared by the public publishing-window check and the editor
status. Preview mode retains its existing bypass; the editor summary describes the public
window independently of preview. Missing records, trash, disabled publishing, future start
and elapsed end are distinct states. Start/end boundaries keep the existing inclusive rules.
The summary names server time and its zone. Navigation membership is separate from whether
a page can be opened directly. The summary is not a network reachability or permission test.

Branch publish/unpublish previews count changed page records in the current language using
the same `Branch` query as execution. Alias originals outside the branch are not included.
Trash previews count tree items and explain that trash applies to all languages and may
break references. Counts describe the moment the preview is rendered; execution reads the
branch again. Existing role checks and action handlers remain in place.

Checks: `php tests/publishing-state.php`, System `tests/display-dates.php`, read-only live
status/preview checks and cancellation. Confirmed destructive operations were not performed
on project data. Version history belongs to the planned editor work; the rights redesign
and the proposed comment feature are not implemented by this follow-up.

## Media replacement and dashboard links

The replacement notice belongs inside `main.uploadStage`, above both preview columns;
it does not span the metadata sidebar. Desktop/tablet use two aligned previews. Narrow
layouts stack the notice, previews and sidebar without horizontal overflow. Upload and
replacement reuse the existing usage template; custom-template and external references
remain outside its detection scope. File details identify original-file properties rather
than the dimensions of a generated format.

Recently edited and applicable To-do links carry the language displayed in their result.
The empty To-do message describes only the checks represented by that widget. A language
matrix and comment tasks remain proposals, not shipped dashboard features.

## Template Settings definition editor

Settings → Template Settings manages the fields shown in the Pages sidebar. New fields
use named kinds (text, whole number, decimal, on/off, choice or group); storage types and
required value children are generated by `Settings\TemplateEditor`. Choices receive named
options; groups currently support text and number fields, matching the existing sidebar.
The sidebar renderer and the template API are unchanged. Creating a field does not add
website behaviour: the project template must read its key.

Visible labels can be renamed without changing template keys. Keys and choice values
remain under Technical connection. Explicit Save changes persists edits and template
visibility; an unsubmitted new-field form creates no record. All templates is distinct
from selecting no templates. The preview has no named or saving controls. Deletion
removes the definition, its children and associated page values in a transaction.

`tests/template-editor.php` exercises all six kinds against a DEV database and rolls back
its fixtures. Supply the DEV configuration path through `FLUXO_TEMPLATE_TEST_CONFIG`.
Shared form serialization is covered by System `tests/form-choice-serialization.mjs`,
including checked radios and array checkboxes for GET, POST and PUT.

Checkpoint (6 November): desktop creation, option renaming, selected-template persistence
and recursive fixture removal were exercised in DEV. Tablet-sized and narrow layouts
were visually inspected. The narrow form submission still needs investigation: after
choosing a group the dialog stayed open, and the mobile layer appeared to have moved
controls outside the original form. Treat the Settings rebuild as work in progress;
next verify form ownership/submission in mobile sheets, group fields, cancellation,
dark mode and sorting before calling the block complete. No project settings were changed.
