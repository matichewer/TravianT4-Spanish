## 1. Oasis simulation entry point

- [x] 1.1 Add the “Simular ataque” action only to unoccupied oasis details
- [x] 1.2 Validate the requested oasis and build the prefilled simulator input

## 2. Simulator integration

- [x] 2.1 Initialize the simulator from the active village, available hero, and oasis animals
- [x] 2.2 Preserve editable Nature quantities on subsequent simulator submissions
- [x] 2.3 Always select the attacking hero in prefilled oasis simulations

## 3. Verification

- [x] 3.1 Add regression coverage for target validation, troop mapping, default hero selection, and editable animal quantities
- [x] 3.2 Run PHP syntax checks, OpenSpec validation, and the simulator regression suite
- [x] 3.3 Verify that `warsim.php?oasis=<wref>` invokes the prefilled simulation path
