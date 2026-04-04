# tools

Developer tooling for rebuilding generated assets and maintaining the plugin.

## File tree
```text
└── build-assets.sh
```

## File roles
- `build-assets.sh` — Developer helper script.

## Maintenance entry point
Use the tooling here from the plugin root so file paths resolve consistently.

Update note: the builder now assembles the router render path and feed render/load path from smaller intent-labelled slices.
