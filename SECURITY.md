# Security Policy

Please report security issues privately to the repository maintainers.

VivaPet uses a strict pet-ID and entity-class allowlist, UUID-based player data, permission checks at summon and follow time, bounded effect and movement settings, and explicit entity cleanup. It does not execute user-supplied code or make outbound network requests.


Additional safeguards in 1.1.0:

- Temporary hide does not alter the saved UUID-to-pet mapping; show always rechecks the rank permission.
- Follow offsets, movement speed, effect refresh and effect duration are clamped before being used.
- Hidden pets are removed from the active tick set and do not consume follow-loop work.
