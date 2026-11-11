# Architecture

- `PetEntity` is a non-persistent cosmetic Living entity with a client resource-pack actor identifier.
- Separate PHP subclasses return the identifiers `vivapet:fox`, `vivapet:dragon`, `vivapet:owl` and `vivapet:rabbit`.
- `PetManager` validates IDs, permissions, pet configuration and effects, stores active entities by player UUID, and updates follow motion.
- Quit, death, world transfer, permission loss, remove and plugin shutdown remove active entities.
- Pet classes are intentionally not registered for chunk persistence. PocketMine's `NeverSavedWithChunkEntity` marker documents this lifecycle.
- The resource pack maps the same actor identifiers to client entity definitions, geometry and textures. PocketMine loads the resource pack stack at server startup, so new/updated packs require a full server restart.

Rank integration is through PocketMine's `Player::hasPermission()` contract. Rank plugins can grant per-pet permissions or `vivapet.pet.all`; VivaPet does not depend on a specific rank plugin.


## Session controls (1.1.0)

- The selected pet ID is stored per player UUID in `players.yml`.
- `/pet hide` closes the active entity but deliberately keeps the saved selection.
- `/pet show` revalidates the current permission before spawning the saved selection.
- `/pet remove` closes the entity and clears the saved selection.
- The manager only keeps active entities in memory; hidden pets are not ticked.
- Follow position offset and vertical offset are configurable and clamped before use.
- Follow velocity eases down as the pet approaches its target to reduce overshooting.
