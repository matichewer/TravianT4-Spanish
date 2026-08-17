## Why

The independent Natar villages went live and immediately exposed a set of defects in how this server names and creates Natar villages. All of them are visible to players or fire the first time an admin touches the panel.

The map tooltip prints a hardcoded `Aldea ` in front of every village name, so a Wonder village reads "Aldea Aldea de la Maravilla" and the new one reads "Aldea Aldea natar". The Natar capital is called "1's village" because the installer passes the username and capital arguments in the wrong order. Every independent village is created with the same name, so a map with a dozen of them will be unreadable. Two admin mods still create Wonder villages owned by `Nature` instead of `Natars`, using hand-written SQL that skips the provisioning the engine now owns. And the repair tool prints the field level it is about to apply rather than the one it measured, which would hide exactly the drift it exists to find.

## What Changes

- Drop the duplicated label from the map tooltips, which also fixes the 13 Wonder villages.
- Give each independent Natar village a name derived from its own seed, so a map full of them stays readable and the name is stable across recomputation.
- Name the Natar capital properly, in the installer and in existing worlds through a migration.
- Make the two admin mods create Wonder villages through the shared Natar helpers instead of hand-written SQL with the wrong owner.
- Report measured field levels next to planned ones in the repair tool, and flag a level above the official maximum instead of passing over it.

## Capabilities

### Modified Capabilities

- `independent-natar-villages`: independent villages get distinguishable names.
- `npc-accounts`: Natar villages are created through the shared helpers, whatever creates them.

## Impact

Touches `Templates/Map/mapview.tpl`, `Templates/Map/mapviewlarge.tpl`, `GameEngine/NatarSettlement.php`, `GameEngine/Admin/Mods/addWW.php`, `GameEngine/Admin/Mods/natarend.php`, `install/include/multihunter.php` and `tools/fix_natar_villages.php`. One data migration renames the existing Natar capital; no schema change.
