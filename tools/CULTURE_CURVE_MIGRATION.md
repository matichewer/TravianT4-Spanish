# Migración de la curva de cultura

La configuración y los saldos deben cambiarse en la misma ventana de mantenimiento.
El código tiene que reconocer ambos modos antes de convertir datos.

## Despliegue de x1 (modo 1) a intermedia (modo 3)

1. Desplegar el código con soporte para el modo 3.
2. Antes de escribir, revisar la simulación:

   ```bash
   docker compose exec -T web php tools/rescale_culture_points.php --desde=1 --hasta=3
   ```

3. En la misma ventana en que `CP` pasa a `3`, aplicar la conversión:

   ```bash
   docker compose exec -T web php tools/rescale_culture_points.php --desde=1 --hasta=3 --aplicar
   ```

4. Ejecutar los chequeos de cultura, expansión por colonos y conquista.

La herramienta usa una transacción, comprueba que ningún jugador cambie de cupo y
anota `1->3` en `admin_log`. Si la conversión ya figura aplicada, se niega a repetirla.
`--forzar` es únicamente una recuperación operativa cuando el registro existe pero se
comprobó externamente que la transacción no escribió los saldos.

## Reversión a x1

Primero simular la operación inversa:

```bash
docker compose exec -T web php tools/rescale_culture_points.php --desde=3 --hasta=1
```

Si la simulación conserva todos los cupos, aplicar la conversión y restaurar `CP = 1`
en la misma ventana:

```bash
docker compose exec -T web php tools/rescale_culture_points.php --desde=3 --hasta=1 --aplicar
```

No se debe activar una curva con saldos medidos contra la otra, ni siquiera de forma
temporal: la interfaz y las validaciones de fundación y conquista leen `CP` en vivo.
