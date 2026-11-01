# GUI follow-up: seven corrections

1. PASS — Live Pages search: entered WORK, selected its result, reactivated the cleared search; WORK returned with selectionStart/End 4. Last query is separately retained in panel state.
2. PASS — Shared dropdown fixtures at 390/1194/1440 px, light/dark; unified-editor tests also check that wrapped controls are not covered. Menu follows the actual toolbar controls without the lower padding gap.
3. PASS — Tags headline visible in the live installation; group headers and actions retained.
4. PASS — Live Website checks navigation contains the magnifying-glass icon, under Pages.
5. PASS — Unified-editor fixture exercises close hover and focus with a nonzero translucent background; focus outline retained.
6. PASS — Live sidebar summaries are about 45 px high (4 px shorter); opened and closed Templates successfully. Shared sidebar selector retained.
7. PASS — Live formatted image exposes Crop on page and opens inline handles/actions; cancelled without saving. Touch fixture verifies Original retains a visible disabled crop action with a format-selection explanation. On-page crop fixtures pass desktop/touch/narrow cases.

Validation: PHP/JS syntax checks and both repositories' diff checks passed. Live browser error log empty; project PHP error log unchanged (29 October, 1710 bytes). No content or media changes saved.

Touch tests are browser emulation/fixtures, not a physical iPad/Safari run. Native device verification remains unavailable here.

## Fixed template images follow-up

PASS — Added the same Crop on page command beside Edit for fixed template images. Live tested on page 1060's gallery image: toolbar shows both commands, crop opens inline handles and actions. No save performed; no browser errors. Shared touch fixture verifies fixed-image crop is enabled for a formatted image, Move stays absent, and fixed-video menu remains unchanged. JS syntax and diff checks pass.
