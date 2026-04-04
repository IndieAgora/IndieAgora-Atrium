# assets/js

Intent-labelled front-end files.

Current load order:
1. `ia-message.api.js`
2. `ia-message.state.js`
3. `ia-message.ui.modals.js`
4. `ia-message.ui.composer.js`
5. `ia-message.boot.js`

`ia-message.boot.js` remains the main orchestrator. Extract new helper concerns into dedicated files and keep boot focused on wiring, activation, and delegation.


`ia-message.ui.composer.js` owns textarea autosize behaviour for send/new/group composers. It also handles clipboard file paste for composer textareas by reusing the existing upload endpoint and inserting returned URLs into the textarea. Keep it SPA-safe and capped so message history remains visible while typing.
