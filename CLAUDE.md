# Norte — Guía de contexto para Claude Code

> SaaS venezolano de **precios vivos**: el emprendedor carga su catálogo una vez y sus precios se
> recalculan solos con la tasa de cada día. Nombre de trabajo del repo: `cal-e`. La marca es
> **Norte**.

Es el cuarto sistema del workspace, junto a `iga-app` (bodega), `sipcop` (minería) y `sivit`
(verificación IT). **`iga-app` es la referencia directa**: de ahí salen el sistema de diseño, el
servicio de tasa y buena parte de los criterios de este documento.

---

## 1. Qué resuelve y para quién

El emprendedor venezolano **vende todos los días, tiene movimiento, y al final del mes no le queda
nada**: la tasa se mueve más rápido de lo que él actualiza precios, así que vende al costo de ayer
sin darse cuenta y se descapitaliza mes a mes. No es un dolor agudo, es una fuga lenta — y por eso
no la nota hasta que ya perdió capital.

**A →** «Reviso mis precios cada dos semanas a mano, siempre voy tarde, y no sé cuánto pierdo.»
**B →** «Mis precios se ajustan solos. Copio mi lista y la mando por WhatsApp en 10 segundos.»

El público: manicurista, pastelera, revendedora de ropa, bodeguero, barbero, delivery. Usa el
teléfono, cobra por Pago Móvil, tiene señal irregular y **cero tolerancia a la fricción**. Cada
pantalla de más es una razón para no volver.

### Lo que NO es (para no perder el norte, valga la marca)

No es un ERP, ni control de stock por unidad, ni facturación fiscal, ni multiusuario en la V1. La
ventaja competitiva es automatizar tasa + margen + entrega recurrente; un ebook lo copian en un
fin de semana, esto no. Ensancharlo antes de validar es la forma más rápida de no lanzar nunca.

---

## 2. Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Frontend | React 19 (JSX), Inertia v2, Vite 8 |
| UI | shadcn/ui + TailwindCSS v4 (CSS-first) + `lucide-react` |
| BD | PostgreSQL 18 (`norte`, puerto 5433) |
| Rutas JS | Ziggy |

Mismo stack que `iga-app` **a propósito**: es lo que permite trasplantar el sistema de diseño y los
servicios ya probados en vez de reescribirlos. No es pereza, es la razón por la que este proyecto
arranca con un motor de precios ya testeado en lugar de con un `hello world`.

```bash
php artisan serve --port=8003    # IGA usa 8002, SIVIT 8001, SIPCOP 8000
npm run dev
php artisan schedule:work        # para ver la tasa entrar sola
```

---

## 3. Multi-inquilino: la decisión que no se puede posponer

**Norte es multi-inquilino desde la primera migración.** IGA es de *un* negocio: `settings` es
global, la tasa es «la de la tienda», los usuarios son empleados de ese local. Meterle inquilinos a
un esquema así después obliga a tocar cada tabla, cada consulta y cada índice — y un solo `where`
olvidado le muestra a un negocio los datos de otro.

### La forma: una base, esquema compartido, `tenant_id` + scope global

Se descartó base-por-inquilino: a 3–5 $/mes por cuenta, mantener y migrar N bases cuesta más que el
producto entero. Con esquema compartido hay una sola migración, un backup y un cron.

El riesgo —una consulta sin filtrar— **no se cubre con disciplina sino con código**:
`App\Models\Concerns\PerteneceAlNegocio` aplica un scope global y rellena el `tenant_id` al crear.
Saltárselo es explícito (`Product::sinNegocio()`), así se ve en el diff quién lo hace y por qué.

### Lo que NO lleva `tenant_id`

`exchange_rates`. **La tasa del BCV es la misma para todo el país**: es infraestructura compartida,
un cron la trae una vez y sirve a toda la plataforma. Ponerle inquilino sería guardar la misma
cifra mil veces y pagar mil llamadas al proveedor por el mismo número. Lo que sí es de cada negocio
es *cuál* usa (`tenants.rate_source`), no *cuánto vale*.

---

## 4. El motor de precios

Todo el producto se reduce a `PrecioService`, que es la **única fórmula del precio** en la
aplicación — misma disciplina que el total de una orden en IGA. Acá importa más: el precio se
muestra en pantalla, se exporta a WhatsApp y se guarda en el historial. Tres fórmulas darían tres
números distintos para el mismo producto, y lo único que tenemos es la confianza del usuario.

```
costo_usd  = cost_currency = 'USD' ? cost : cost / cost_rate
precio_usd = costo_usd × (1 + margin/100)
precio_bs  = redondear_hacia_arriba(precio_usd × tasa_del_negocio)
```

### Por qué `cost_rate` es la columna más importante del esquema

El usuario declara su costo en la moneda que le sirva: la manicurista compró el esmalte en dólares,
la bodeguera pagó la harina en bolívares. Obligarlo a convertir sería la primera fricción.

Pero **un costo en bolívares no es un costo estable**: son bolívares de un día concreto. Si mañana
sube la tasa y seguimos calculando sobre esos mismos bolívares, el precio sube en Bs pero en
dólares vale menos — exactamente la descapitalización que venimos a resolver. Guardar solo el
número sería reproducir el problema del usuario dentro de la herramienta.

Por eso, un costo en bolívares guarda **también la tasa del día en que se declaró**. Con ella se
ancla a dólares una sola vez, y de ahí en adelante el precio en Bs se recalcula solo. *El costo en
dólares es el ancla; la tasa del día es la corriente.* Hay un test dedicado a esto
(`test_un_costo_en_bolivares_se_ancla_con_la_tasa_del_dia_en_que_se_declaro`) y no debe romperse.

### Redondeo: hacia arriba, siempre

Un precio de 187,43 Bs no se puede cobrar en un mostrador — nadie tiene ese vuelto. El comerciante
lo redondea igual; si la herramienta no lo hace, lo hace él a mano y volvimos al trabajo que
vinimos a quitarle. Y se redondea **hacia arriba**: hacia abajo estaríamos comiéndonos el margen
que este producto existe para proteger. Un céntimo por venta, todos los días, es justo la fuga
lenta contra la que se construyó esto.

### Sin tasa no se inventa una tasa

`tasaDe()` devuelve `0` cuando no hay ninguna cargada, y el llamador decide. Un número inventado
acá se convierte en un precio mal cobrado en el mostrador.

---

## 5. La tasa del día

`TasaService` es un trasplante de `ExchangeRateService` de IGA, donde lleva tiempo en producción.
Las dos reglas que lo hacen confiable — y que acá pesan más, porque la tasa **es** el producto:

1. **Si ningún proveedor responde, no se escribe nada.** Es preferible quedarse con la última tasa
   buena que pisarla con basura: en IGA un error afectaba a una bodega; acá afectaría a todos los
   clientes de la plataforma el mismo día.
2. **El valor se valida contra un rango de cordura** (`exchange.min`/`max`) antes de guardarse. Un
   cambio de formato en el JSON del proveedor se manifiesta como un número absurdo, no como un
   error HTTP: sin el rango entraría igual.

El BCV no publica API propia; `config/exchange.php` configura dos espejos públicos en cascada.

**La interfaz siempre dice de cuándo es la tasa** (`actualizada_hace`, `es_de_hoy`). Con señal
irregular el cron puede no haber corrido, y el usuario tiene derecho a saber que está viendo la de
ayer antes de mandarle la lista a un cliente. Mostrar la última buena diciendo su edad es mejor que
romperse, y muchísimo mejor que mentir en silencio.

---

## 6. Marca

**Norte**, de «no perder el norte» — dicho venezolano, cálido y directo. La promesa es dirección:
la tasa se mueve, tú sabes dónde estás parado.

- **Isotipo**: la aguja de la brújula. Asimétrica a propósito — la mitad norte más larga y afilada,
  la sur más corta y ancha. Esa asimetría es lo que la hace leerse como aguja y no como diamante.
  Vive como SVG en código (`Components/Marca/LogoNorte.jsx`), igual que en IGA.
- **Color**: ámbar marigold (`--primary`) sobre azul medianoche. El ámbar es la punta que señala.
  **Lleva texto oscuro encima**, nunca blanco: ámbar con blanco no pasa contraste AA.
- **Modo oscuro por defecto.** La marca se diseñó sobre azul medianoche y el público usa la app en
  la calle, muchas veces de noche cerrando el negocio. El tema se aplica en un script del blade
  **antes de que React monte**: si esperara al primer render, la pantalla de entrada parpadearía
  del tema equivocado al correcto.
- El arte del panel de acceso es **generado, no una imagen**: pesa kilobytes en vez de cientos y se
  re-tematiza solo. Con datos caros y señal irregular, cargar una foto de fondo en la pantalla de
  entrada es cobrarle megas al usuario antes de darle nada.

---

## 7. Qué se trajo de `iga-app` y qué no

**Sí se trajo** (y por qué vale):

- El sistema de diseño completo: primitivos de shadcn, tokens de Tailwind v4, `ConfirmDialog`,
  `ThemeToggle`, `Formatter.js` (que redondea en vez de truncar — la versión vieja mostraba 1.999
  como 1,99 y no cuadraba con el backend).
- `ExchangeRateService` → `TasaService`.
- Los criterios: una sola fórmula para el dinero, servicios en `app/Services`, controladores que
  devuelven `inertia()` o JSON, tests contra Postgres con nombres que explican *por qué* importa
  cada regla.

**No se trajo**: nada que asuma un solo negocio. El punto de venta, el kardex, el arqueo y el fiado
de IGA son excelentes y están probados, pero son de la fase 2 de Norte (plan superior) y hay que
portarlos **con `tenant_id`**, no copiarlos tal cual.

---

## 8. Estado actual

Hecho:

- Esqueleto Laravel 13 + Inertia + React 19 + Tailwind v4, con el sistema de diseño de IGA.
- Multi-inquilino: `tenants`, `users`, scope global, alta de negocio+usuario en una transacción.
- `exchange_rates` compartida + `TasaService`.
- `products` + `price_snapshots` con el modelo de costo anclado.
- `PrecioService` con 9 tests verdes.
- Marca Norte: isotipo, paleta, tema oscuro por defecto.
- Login y registro con el panel de marca; panel con la tasa del día y la lista calculada.

### Lo que sigue, en orden

1. **Alta y edición de productos** — hoy el panel muestra el estado vacío. Es lo que falta para que
   el «aha» ocurra: cargar un producto y ver su precio al día.
2. **Exportar a WhatsApp** — texto plano agrupado por categoría. Es la mitad de la promesa B.
3. **Cron de la tasa** (`schedule` + comando) y foto diaria en `price_snapshots`.
4. **Historial de precios** — la gráfica que hace que el usuario *crea* que la herramienta trabaja.
5. **Suscripción** — ver pendientes.

### Pendientes que necesitan decisión del negocio

- **Cobro.** El plan es Guaybo/Komvii en Pago Móvil, no Stripe. Falta confirmar si permiten cobro
  recurrente automático o si cada mes se activa a mano. Eso define el control de acceso: qué pasa
  exactamente el día que alguien no paga el mes 2.
- **Entrar con Google.** El botón está en la interfaz porque el público no recuerda contraseñas,
  pero `laravel/socialite` **todavía no soporta Laravel 13**: la ruta existe y avisa en vez de
  romperse (mismo criterio que IGA con el router y la clave de IA). Revisar cuando publiquen
  compatibilidad.
- **Recuperación de contraseña**: el enlace avisa que no está lista. Falta configurar correo.
- **PWA**: decidido web ligera; falta el manifest y el service worker para que funcione con señal
  intermitente.

---

## 9. Comandos

```bash
php artisan migrate:fresh --seed
php artisan test
./vendor/bin/pint
npm run build
```

**Tests**: contra Postgres (`norte_test`), no sqlite en memoria — las diferencias de dialecto tienen
que salir en las pruebas. Crear la base una vez con `createdb -U postgres -p 5433 norte_test`.
