# Renta de 5ta Categoría — cómo la calcula CATA-Recibo

Referencia completa de cómo el sistema retiene el Impuesto a la Renta de
Quinta Categoría en la planilla, mes a mes. Complementa a
[MODELO-DE-DATOS.md](MODELO-DE-DATOS.md) y [CALIDAD-Y-PRUEBAS.md](CALIDAD-Y-PRUEBAS.md).

## 1. Qué es

Es el impuesto a la renta que se retiene **de la boleta**, cada mes, a
cualquier trabajador en planilla (dependiente). No lo paga el colegio: lo
paga el trabajador, y el colegio actúa de **agente retenedor** — se lo
descuenta y se lo entrega a SUNAT en su nombre.

## 2. Base legal

**Art. 40 del Reglamento de la Ley del Impuesto a la Renta (D.S. 122-94-EF)**.
Fuente verificada además contra orientacion.sunat.gob.pe y casos prácticos de
contadores (2026).

## 3. La idea central: se proyecta el año, no el mes suelto

Cada mes, el sistema **proyecta cuánto va a ganar el trabajador en todo el
año**, calcula el impuesto sobre esa proyección, y de ahí saca cuánto le
toca retener **este** mes. En enero se proyecta con lo que se sabe hasta
ahora (sueldo actual × los meses que faltan); en diciembre ya no se
proyecta nada, se usa el ingreso real de los 12 meses.

## 4. La tabla del año

| Mes | × proyección | ÷ retención | Corte de lo ya retenido |
|---|---|---|---|
| Enero | 12 | 12 | — |
| Febrero | 11 | 12 | — |
| Marzo | 10 | 12 | — |
| Abril | 9 | 9 | hasta marzo |
| Mayo | 8 | 8 | hasta abril |
| Junio | 7 | 8 | hasta abril |
| Julio | 6 | 8 | hasta abril |
| Agosto | 5 | 5 | hasta julio |
| Setiembre | 4 | 4 | hasta agosto |
| Octubre | 3 | 4 | hasta agosto |
| Noviembre | 2 | 4 | hasta agosto |
| **Diciembre** | real | 1 | regulariza el año |

En el código: `TRAMOS_RENTA_5TA` en `app/Traits/CalculaConceptosPlanilla.php`.

## 5. El mínimo exento y los tramos

La UIT del sistema es **S/ 5,500** (`private float $uitValor`, mismo
archivo). Si la proyección anual **no pasa de 7 UIT (S/ 38,500)**, no se
retiene nada.

Sobre el exceso, los tramos son **acumulativos** (cada uno paga su propia
tasa, no todo a la más alta):

| Tramo (sobre el exceso de 7 UIT) | Tasa |
|---|---|
| Hasta 5 UIT (S/ 27,500) | 8 % |
| De 5 a 20 UIT | 14 % |
| De 20 a 35 UIT | 17 % |
| De 35 a 45 UIT | 20 % |
| Más de 45 UIT | 30 % |

En el código: `aplicarTramosImpuestoRenta()`.

## 6. Qué entra en la proyección

- **Sueldo base + bonificaciones del mes + Asignación Familiar** (si tiene
  hijos: es remunerativa, también paga renta).
- Las **dos gratificaciones** del año (julio y diciembre), prorrateadas si
  entró a mitad de año (`calcularGratificacion()`).
- **Ingresos extraordinarios ya pagados este año** (bonos, subsidios),
  leídos de la propia base con `ingresosExtraordinariosAcumulados()`.

A partir de **abril** se resta lo que ya se retuvo en los meses anteriores
(`retencionesRenta5taAcumuladas()`), para no cobrar dos veces lo mismo. En
**diciembre** se usa el ingreso real de los 12 meses y se regulariza la
diferencia.

## 7. Simplificación consciente (documentada en el propio código)

Los ingresos extraordinarios del **mismo mes en curso** se suman a la
proyección igual que los de meses anteriores, en vez de aplicarles el
sub-procedimiento aparte de "retención adicional" que exige la norma para
pagos extraordinarios del mismo mes. La diferencia se autocorrige en la
regularización de diciembre.

## 8. Ejemplo resuelto

Sueldo S/ 5,000, sin hijos, mes de enero:

```
Sueldo × 12 meses                     60,000.00
2 gratificaciones (5,450 c/u)         10,900.00
Proyección anual                      70,900.00
Exceso sobre 7 UIT (38,500)           32,400.00
5 UIT × 8 %                            2,200.00
4,900 restante × 14 %                    686.00
Impuesto anual                         2,886.00
Retención de enero (÷ 12)             S/ 240.50
```

Diciembre, sin retenciones previas, regulariza con el **impuesto anual
completo**: S/ 2,886.00 de una sola vez.

Abril, con S/ 240.50 ya retenidos en enero: proyecta 9 meses
(45,000 + 10,900 = 55,900 − 38,500 = 17,400 → impuesto 1,392), resta lo
retenido y divide entre 9 → **S/ 127.94**.

## 9. Cómo interactúa con el prorrateo por días hábiles

Desde el 2026-09-28, el sueldo del mes de quien entra o sale a mitad de mes
se prorratea por **días hábiles** (lunes a viernes), no por días de
calendario — ver `repartoDeDiasDelMes()` y el porqué en su propio
comentario. Ese sueldo ya prorrateado es el que entra a
`calcularRenta5taCategoria()` como `$sueldoBase`: si alguien entra a mitad
de mes, su proyección de ese mes usa el sueldo ya recortado, no el sueldo
completo de su ficha.

## 10. Dónde está en el código

| Qué | Archivo | Método |
|---|---|---|
| El cálculo completo | `app/Traits/CalculaConceptosPlanilla.php` | `calcularRenta5taCategoria()` |
| Los tramos progresivos | mismo archivo | `aplicarTramosImpuestoRenta()` |
| Lo ya retenido este año | mismo archivo | `retencionesRenta5taAcumuladas()` |
| Ingresos extraordinarios del año | mismo archivo | `ingresosExtraordinariosAcumulados()` |
| Se guarda como línea de la boleta, o se borra si da 0 | mismo archivo | `generarYPersistirRenta5ta()` |
| El nombre del concepto, en un solo sitio | `app/Support/ConceptosDePago.php` | `RENTA_5TA` |

## 11. Las pruebas que lo verifican

`tests/Unit/MotorDeCalculoTest.php` — 6 pruebas, con los valores calculados
a mano contra la fórmula legal (no solo contra lo que hace el código):

- `test_sueldo_bajo_no_paga_renta_de_5ta`
- `test_renta_de_enero_proyecta_el_ano_y_aplica_los_tramos`
- `test_la_asignacion_familiar_tambien_paga_renta`
- `test_a_mayor_sueldo_mayor_retencion`
- `test_diciembre_regulariza_con_el_impuesto_anual_completo`
- `test_abril_descuenta_lo_ya_retenido_de_enero_a_marzo`

```bash
cd CR-Backend
php vendor/bin/phpunit --filter MotorDeCalculoTest
```

## 12. Lo que le puedes decir a un evaluador, en una frase

> "El sistema no aplica una tasa fija: proyecta el ingreso anual del
> trabajador mes a mes según el Art. 40 del reglamento, aplica los tramos
> progresivos de SUNAT sobre el exceso de 7 UIT, descuenta lo ya retenido en
> meses anteriores, y en diciembre regulariza con el ingreso real del año."
