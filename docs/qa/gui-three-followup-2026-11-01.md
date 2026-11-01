# GUI follow-up: white password, returning statusbar, empty dashboard columns

1. PASS — Password accordion uses theme canvas for its surface and summary. Running Dev account expanded visually; component fixtures 390/1194/1440 light/dark passed. No credentials changed.
2. PASS — Mobile Safari bottom edge was retained on desktop, covering the statusbar. Remove it when mobile media query ends. Running Dev resized 390 → 1440: edge removed, footer visible at 34px; screenshot saved. Targeted real-controller regression repeated responsive transitions.
3. PASS — Empty dashboard columns show a highlight dashed/tinted drop target only while widgetDragging. Existing drop endpoint retained. Dashboard shared drag and responsive layout suite passed; empty target visibility/height/border checked and screenshot visually inspected. No real user layout reordered.

Limitations: no physical iPad available. Full legacy mobile-shell suite stops at an existing base-view visibility assertion (line 49), before changed desktop cleanup; targeted mobile-statusbar-return suite covers this fix. Dev console: existing duplicates panel parent-wiring warning, no new errors. PHP log unchanged (Oct 29, 1710 bytes). Tests use local fixtures where noted, not real Safari.
