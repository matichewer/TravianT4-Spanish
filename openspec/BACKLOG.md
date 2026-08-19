# Backlog

Trabajo identificado y todavía no propuesto como cambio. Cada entrada tiene lo suficiente
para retomarla en frío. Cuando una arranca, se convierte en un cambio bajo `changes/` y se
borra de acá.

Los cambios activos **no** viven en este archivo: `openspec list` es la fuente de verdad.

---

*(vacío — las dos entradas que había se implementaron el 2026-08-19)*

## Hecho

- **Las Maravillas tienen residencia y en el T4 oficial no.** Resuelto: el instalador ya no
  la construye y `tools/migrations.sql` trae la migración para mundos ya instalados, aplicada
  en producción. Cubierto por la prueba de `getConquestEligibility()`, que pasó de devolver
  `residence` a dejar seguir.
- **`canClaimArtifact()` no comprueba nada.** Resuelto: leía una variable antes de asignarla,
  cargaba los campos del defensor sin usarlos y medía la tesorería de la aldea equivocada;
  además el artefacto no se mudaba a la aldea del atacante. Cubierto por
  `tools/check_artifact_claim.php`.
