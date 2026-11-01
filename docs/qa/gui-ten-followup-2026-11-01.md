# GUI follow-up, 1 November 2026

Scope: the user's numbered 1–10 block (search caret, sidebar, classes, progress, module actions, website tools, held dashboard drag, image editor menus).

| Nr. | Status | Verification |
| --- | --- | --- |
| 1 | PASS | Dev browser: entered query, closed search, reopened it; selectionStart and selectionEnd equal the retained text length. Initial-open focus only; result refresh does not reposition the caret. |
| 2 | PASS | Shared sidebar summaries enlarged by 4 px. Real media sidebar opened; fixture summaries clicked closed/open at 390/1194/1440 px in light/dark. |
| 3 | PASS | WYSIWYG fixture with the actual menus: cmsMediaOn does not mark the CSS action selected or appear in its input. Authored class save still works. Space-separated placeholder; arrow behind class form. Synthetic touch test, not physical iPad. |
| 4 | PASS | Existing upload/backup progress markup retained. Both counters checked centered, duplicate clipped counter hidden. Six responsive/light/dark rendered fixtures; no real upload/restore initiated. |
| 5 | PASS | Dev Backup, Account and Tags use one root action toolbar with shared gutters; widget headers retained. Dev screenshots at desktop/tablet and Backup at 390 px. Mobile reuses existing top title. No account or backup data changed. |
| 6 | PASS | Actual settings-group markup matched by shared sidebar label rule. Rendered label .75rem in all six fixtures, input editable. |
| 7 | PASS | website-menu.php: tool hidden without CMS-supplied children. Website checks inspect Pages links and 404 requests; system automatic cleanup is separate. |
| 8 | BLOCKED | Implemented handoff movement guard. Real shared drag engine test passes: original held title remains under pointer; initial handoff and 2 px touch jitter do not select a drop target; normal drops and empty-column targets still work. Physical iPad/Safari is unavailable; original-device reproduction remains to verify. |
| 9 | PASS | Dev media 1515 opened in edit overlay: X visibly contrasts against theme frame; clicked Close without modifying media. |
| 10 | PASS | Dev format dropdown measured exactly 4 px below trigger; screenshot inspected. Shared split-menu anchoring now uses trigger bottom, not padded toolbar bottom. |

Commands: system/tests/dashboard-responsive-ui.mjs; cms/tests/editor-touch-followup-ui.mjs; cms/tests/gui-current-ten-ui.mjs; cms/tests/gui-round-7-ui.mjs; cms/tests/website-menu.php; syntax checks of changed JS/PHP.

Dev browser: no JavaScript errors observed. Existing warning about duplicates' missing cleanup parent remains. Project PHP_errors.log unchanged (29 Oct 17:18:35, 1710 bytes). No full network trace or physical-device Safari test was performed. Browser viewport reset and temporary tab closed.

The block is not fully accepted while item 8 lacks the physical-device check. No commit created.
