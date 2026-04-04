# Notes: assets / js / split / search

## What changed in the 0.3.59 architecture pass
- Search source slices were rebuilt from the live runtime bundle into suggestions, result rendering, click handling, loading, and export files.

## File/function index
- `_search.body.js` — Functions: debounce, stripMarkup, avatarHTML, iconSVG, openConnectProfile
- `search.export.js` — Window exports: IA_DISCUSS_UI_SEARCH
- `search.results_clicks.js` — Functions: bindResultsClicks
- `search.results_load_and_page.js` — Functions: loadResults, renderSearchPageInto
- `search.results_render.js` — Functions: resultsShellHTML, setActiveType, iconBubble, renderResultRow
- `search.suggestions_box.js` — Functions: ensureSuggestBox, positionSuggestBox, hideSuggest, showSuggest, suggestGroup
- `search.suggestions_interactions.js` — Functions: bindSearchBox

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.
