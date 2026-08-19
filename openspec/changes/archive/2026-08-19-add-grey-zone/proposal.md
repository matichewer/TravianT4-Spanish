## Why

The grey zone is the last piece of official T4 this server was missing: the centre of the map where founding a village wakes the Natars. Fourteen waves arrive about a day later, the first sweeping away any defence and the rest carrying catapults, and the village survives only by being big enough not to be flattened to zero population.

It could not be added the official way. Official draws a disc of radius 22 around (0|0), but this world was already three months into play and its four players had settled their main villages *closer to the centre than the nearest Wonder village* — 5.4, 8.1 and 8.6 tiles against 12.0. Any disc containing a Wonder village contains them too, and any disc excluding them contains no Wonder village at all, which leaves a hazard zone with nothing worth going there for.

## What Changes

- Add the grey zone as a configurable **ring** rather than a disc, defaulting to 10–16 on this world. That is the only geometry that reproduces the official split of Wonder villages — five inside, eight outside — while leaving every existing village untouched.
- Founding a village inside it schedules fourteen Natar waves arriving after a speed-scaled day, invented rather than deducted, exactly like the Wonder attack waves.
- Mark the zone in the map tooltip on free valleys, so founding there is a decision rather than an ambush.
- Delete the `attacks` rows belonging to movements a razed village takes with it, which this feature made visible: fourteen waves against a new village raze it partway through and the remaining waves left their rows orphaned.

- Generate the official terrain — 15-croppers and 50% oases — inside the zone **when a world is installed**. The generator had a dead `isgrayfield()` that was never called, so the terrain was half-built in the upstream project and every world so far got uniform ground. This changes nothing on a running world, where `wdata` is written once at install, but it means the next one is right.

Explicitly out of scope: rewriting the terrain of *this* world, because changing `wdata` under villages already built on it would move the ground beneath them. And the culture-point restriction, which would only have applied to villages founded after the change and would have created two classes of village in the same zone.

## Capabilities

### New Capabilities

- `grey-zone`: Defines the hazardous region at the centre of the map and what founding a village there costs.

## Impact

Adds `GameEngine/GreyZone.php`, hooks the settlement completion path in `GameEngine/Automation.php`, and marks free valleys in both map templates. No schema change and nothing to run on an existing world: the zone only ever reacts to a village being founded.

The two radii are constants. A fresh world should set them to `0` and `22`, which is the official disc; the installer places starting villages far from the centre, so nothing conflicts there.
