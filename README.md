# VivaPet 2.0.0

PocketMine-MP API 5 companion pets. No resource pack or .mcpack is installed. Pets use built-in Bedrock actor identifiers; non-native species (lion, tiger, shark, owl, unicorn, etc.) are themed aliases rendered using the nearest vanilla model, not custom geometry.

Features: 65 pet IDs, per-pet rank permissions, one active pet per player, smooth following, distance recovery, potion effects, optional particles, UUID persistence, hide/show/toggle, random unlocked pet, paginated list, owner-only visibility, damage/collision protection and bounded configuration.

Install VivaPet.phar in plugins/. Grant vivapet.command plus selected vivapet.pet.<id> nodes to ranks. vivapet.pet.all unlocks all pets. Use /pet list, /pet summon fox, /pet random, /pet hide, /pet show, /pet remove, /pet info fox and /pet reload.

Config: plugin_data/VivaPet/config.yml. No files need to be placed in resource_packs/. For unique 3D models or textures, a client resource pack is technically required; this build intentionally avoids it.

Build: php -d phar.readonly=0 tools/build_phar.php; python3 tools/package_assets.py. Runtime end-to-end testing still needs a live PocketMine server and Bedrock client.
