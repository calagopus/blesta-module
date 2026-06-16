![Calagopus Logo](https://calagopus.com/fulllogo.svg)

# Calagopus Blesta Module

<https://calagopus.com>

A server provisioning module for the [Calagopus](https://calagopus.com) game server panel.
It provisions and manages servers from Blesta, with support for nests/eggs, location- or
node-based deployment, egg variables, and custom (extension-added) feature limits.

> This module is strongly based on the official Blesta Pterodactyl module @ <https://github.com/blesta/module-pterodactyl>, with modifications to work with the Calagopus panel API and support for Calagopus-specific features.

## Installation

1. Upload the contents of this repository to your Blesta installation at:
   ```sh
   /path/to/blesta/components/modules/calagopus/
   ```

2. In Blesta, go to **Settings → Company → Modules → Available**, find **Calagopus**, and click **Install**.

3. Click **Manage** on the Calagopus module, then **Add Server** and configure:
   - **Server Label**: A friendly name for this panel
   - **Panel URL**: Your Calagopus panel URL (e.g. `https://panel.example.com`). HTTPS is
     used if no protocol is given.
   - **API Key**: Your Calagopus admin API key
   - **Default User Language**: The default language assigned to created panel users (e.g. `en`)

   Saving validates the connection against the panel.

4. Create a **Package** and select the **Calagopus** module. Choose the nest, egg, deployment
   target (a specific node, or one or more locations), resource limits, feature limits, and any
   egg variables. Egg variables marked **(display)** can be edited by clients during checkout.

## Deployment Modes

- **Node**: Select a specific node and the module will allocate the first available allocation on it.
- **Auto (locations)**: Leave the node as *Auto* and select one or more locations; Calagopus will
  pick a node and allocation automatically.

## Custom Feature Limits

Calagopus supports extension-added feature limits beyond the standard
allocations/databases/backups/schedules. Provide them in the **Custom Feature Limits** field
using a comma-separated `key:value` format, for example:

```
plugins:5,worlds:3
```

## Migrating from the Pterodactyl Module

If you previously sold servers through the official Blesta **Pterodactyl** module and have
migrated your panel to Calagopus, the module can import your existing packages and services.

Prerequisites:

1. Your servers, users, nests, eggs, and locations have already been migrated to the Calagopus
   panel. Nests, eggs, and locations are matched **by name**, and servers are matched by their
   external ID (with a fallback through the server UUID), so they must exist on the Calagopus
   panel before importing.
2. The Pterodactyl module is still installed and its panel is still reachable (it is used to
   translate IDs to names/UUIDs during the import).
3. You have added at least one Calagopus server (module row) under **Manage** on this module.

To import, go to **Settings → Company → Modules → Installed → Calagopus → Manage**. The
**Import from Pterodactyl** section lists each Pterodactyl server; choose the Calagopus server
to import each one into and click **Import**.

The import:

- Reassigns each package to this module, converting its settings (`io` → `IO Weight`,
  `image` → `Docker Image`, `startup` → `Startup Command`, databases/allocations/backups limits,
  and all egg variables with their client-display flags). The package's Pterodactyl location
  becomes its Calagopus location for automatic deployment.
- Rewrites each service's fields, linking it to the matching server on the Calagopus panel
  (server UUID, IP, and port are refreshed from the panel).
- Copies the tracked panel username for each migrated client.

Anything that cannot be matched is skipped and reported in the results, so the import can be
re-run after fixing the cause (already-migrated packages are no longer assigned to the
Pterodactyl module and are not touched again).

Notes:

- Packages that deployed via a Pterodactyl **server group** are assigned directly to the
  selected Calagopus server; recreate module groups manually if you use them.
- Pterodactyl's `port_range`, `dedicated_ip`, and `pack_id` settings have no Calagopus
  equivalent and are dropped. Ports and dedicated IPs are managed via Panel Egg Configurations.
- Configurable options (e.g. a `memory` config option) keep working when their names match the
  Calagopus field names; options named `nest_id`/`egg_id`/`location_id`/`io`/`image`/`startup`/
  `databases`/`allocations`/`backups` must be recreated with the new names
  (`nest_uuid`, `egg_uuid`, `io_weight`, `docker_image`, `startup_command`, `database_limit`,
  `allocations_limit`, `backup_limit`).
- After verifying the import, you can uninstall the Pterodactyl module.
