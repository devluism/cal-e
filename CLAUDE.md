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

## 7. Suscripción y cobro

### Lo que importa no es cómo entra la plata

En Venezuela el riel de cobro es la parte inestable: hoy es Pago Móvil reportado a mano, mañana
puede ser C2P con acuerdo bancario, Binance Pay o una pasarela local. Lo que **no** cambia es la
máquina de estados del acceso. Por eso están separados:

- `subscriptions` manda sobre el acceso y **no sabe nada de bancos**.
- `payments` registra que entró dinero, por el riel que sea.

Conectar confirmación automática mañana es llamar a `SuscripcionService::confirmar()` desde un
webhook en vez de desde el comando `pagos`. El control de acceso no se entera.

### La máquina de estados

```text
prueba (14 días) → activa → gracia (3 días) → suspendida
                      ↑                            │
                      └────────── pago ────────────┘
```

- **El estado se recalcula al leer**, no en un cron. Una suscripción no puede quedarse diciendo
  «activa» tres semanas después de vencer porque el programador de tareas no corrió. El cron sirve
  para avisar, no para que la regla se cumpla.
- **La gracia no es generosidad, es realismo.** El público cobra por Pago Móvil y paga cuando puede:
  cortarle a la medianoche del día que vence es perder a alguien que iba a pagar el jueves.
- **Suspendida no es «afuera».** Sigue entrando y viendo su catálogo — sus costos y márgenes los
  cargó él. Pierde la parte viva (precio con la tasa de hoy y exportación), que es lo que paga.
  Borrarle los datos o dejarlo fuera del todo garantiza que no vuelva.
- **La pantalla de suscripción NO lleva el middleware `suscrito`**: es justo a la que hay que poder
  llegar cuando venció. Protegerla dejaría al cliente sin forma de pagar.

### El cobro

`SuscripcionService::cobroDe()` le muestra el monto **ya convertido a bolívares con la tasa del
día**: obligarlo a multiplicar sería pedirle justo la cuenta de la que este producto lo libera.

Al reportar, el monto se **congela en las dos monedas con la tasa usada** — la misma lección de
`order_payments` en IGA. Si se recalculara al leer, el pago de ayer aparecería hoy con otra cifra y
el cliente diría, con razón, que pagó lo que le pidieron.

- **Reportar no da acceso.** Queda en `reportado` hasta que se coteje. A 3–5 $/mes no compensa
  perseguir el fraude: es más barato confirmar.
- **La referencia es única** en toda la plataforma: es lo que impide estirar la suscripción
  reportando el mismo comprobante dos veces.
- **Pagar antes de vencer no pierde los días que quedaban** (se extiende desde el vencimiento);
  pagar ya vencido cuenta desde hoy, porque regalarle el tiempo suspendido sería cobrarle por días
  que no usó.

```bash
php artisan pagos pendientes        # qué hay por cotejar
php artisan pagos confirmar 12      # activa la suscripción
php artisan pagos rechazar 12 --motivo="no aparece en el banco"
```

Va por consola y no por una pantalla de administración porque con las primeras decenas de clientes
es más rápido —se abre el estado de cuenta del banco al lado— y una consola de administración es un
producto en sí mismo. Cuando haya volumen, la pantalla llama al mismo servicio.

**Antes de cobrarle a nadie**: reemplazar los datos de ejemplo de `PaymentAccount` en `PlanSeeder`
por el Pago Móvil real.

---

## 8. El catálogo

### La vista previa en vivo es la funcionalidad, no un adorno

El precio aparece **mientras el usuario teclea** el costo y el margen. No está detrás de un botón
«calcular» ni espera al servidor: ese instante —ver el número formarse solo— es cuando se entiende
para qué sirve Norte. Con la señal que tiene este público, una ida al servidor por pulsación haría
que la cifra llegara tarde y el efecto se perdiera.

Eso obliga a tener la fórmula **dos veces**: `PrecioService` (PHP, autoridad) y `Utils/Precio.js`
(espejo, solo para la previa). Es el mismo trato que IGA hace con `calcularPrecio()` en
`Formatter.js`, y por eso ambos archivos llevan el aviso: **si se toca una, hay que tocar la otra**.
Lo que se guarda y lo que se exporta sale siempre del servidor.

### El anclaje se explica, no se pide

Cuando el costo se declara en bolívares aparece «¿A qué tasa lo compraste?» **prellenado con la
tasa de hoy** —el caso común es «esto lo compré ahorita»— y debajo, la razón en el idioma del
usuario: *«tu costo queda anclado en $1,47 y tu precio sube solo cuando suba el dólar; sin esto
irías perdiendo sin darte cuenta»*. Sin esa frase el campo parece un trámite y la gente pone
cualquier cosa; con ella, es lo que le vendimos.

### Ver está abierto, editar no

Listar el catálogo **no** exige suscripción vigente: esos costos y márgenes los cargó el usuario y
son suyos. Lo que se paga es el precio calculado, y `ProductoController::list()` simplemente **no lo
incluye en la respuesta** cuando la suscripción venció — calcularlo y esconderlo con CSS no sería
esconderlo. Modificar el catálogo sí va detrás de `suscrito`.

### `step="any"` en todos los campos numéricos

No es descuido. Con un `step` fijo el navegador rechaza los valores que no caen en la rejilla, **en
silencio y con un mensaje propio en inglés**. Pasó de verdad: el formulario prellenaba la tasa del
día (814,6908, cuatro decimales como la publica el BCV) en un campo con `step="0.01"`, y guardar no
hacía nada. Quien valida es el servidor; el navegador solo elige el teclado (`inputMode`).

---

## 9. Exportar a WhatsApp

Es la otra mitad de la promesa B del CLAUDE.md: «copio mi lista y la mando en 10 segundos».

### Formato y acción, separados

`Utils/ExportarWhatsApp.js` tiene tres funciones que no se mezclan:

- `generarTextoWhatsApp()` — arma el texto. Pura: mismo catálogo adentro, mismo texto afuera.
- `copiarAlPortapapeles()` — la acción de copiar, con respaldo a `execCommand` si
  `navigator.clipboard` no existe (contexto no seguro, WebView viejo — nada raro en este
  público).
- `abrirWhatsApp()` — abre `wa.me/?text=...` **sin número de destino**. Quien manda la lista
  es el negocio; a quién se la manda —un cliente puntual, un grupo, su propio estado— lo
  elige él en la app, no la herramienta.

Separarlas es lo que permite ofrecer las dos vías en el panel («Copiar» y «WhatsApp») sin
duplicar el formato, y lo que haría trivial agregar un tercer destino (Instagram, un correo)
el día que haga falta.

### No se exporta con la tasa en cero

Si no hay tasa cargada, los dos botones se cortan antes de generar el texto y avisan en vez
de dejar salir una lista con puros `0,00 Bs`. Es la misma regla de `PrecioService::tasaDe()`
aplicada un nivel más arriba: **mostrar la última tasa buena diciendo su edad es aceptable;
mandarle a un cliente una lista con precios inventados o en cero no lo es**, y para cuando el
negocio se diera cuenta ya estaría circulando.

### Un aviso que quedaba roto en la primera instalación

Al construir esto se encontró que `tasa.actualizada` salía `null` cuando la tabla
`exchange_rates` estaba vacía —cualquier instalación nueva, antes del primer
`tasa:sync`— y el frontend armaba el aviso como *«Esta tasa se actualizó .»*, con un hueco.
Se agregó `tasa.nunca_cargada` (compartido en `HandleInertiaRequests`) para que ese caso
tenga su propio mensaje: *«Todavía no hay tasa cargada»*, distinto de *«se actualizó ayer»*.

---

## 10. Qué se trajo de `iga-app` y qué no

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

## 11. Estado actual

Hecho:

- Esqueleto Laravel 13 + Inertia + React 19 + Tailwind v4, con el sistema de diseño de IGA.
- Multi-inquilino: `tenants`, `users`, scope global, alta de negocio+usuario en una transacción.
- `exchange_rates` compartida + `TasaService`.
- `products` + `price_snapshots` con el modelo de costo anclado.
- `PrecioService` y `SuscripcionService`, con 47 tests verdes.
- Marca Norte: isotipo, paleta, tema oscuro por defecto.
- Login y registro con el panel de marca; panel con la tasa del día y la lista calculada.
- **Tasa entrando de verdad**: `php artisan tasa:sync` + programador. Verificado contra el
  proveedor real.
- **Cobro**: planes, suscripción con máquina de estados, reporte de Pago Móvil con el monto
  convertido a la tasa del día, y confirmación por consola (`php artisan pagos`).
- **Catálogo**: alta, edición y borrado de productos, con **vista previa del precio en vivo**
  mientras se teclea — es el momento en que el usuario entiende qué hace la herramienta.
- **Exportar a WhatsApp**: copiar o abrir WhatsApp con la lista ya formateada. Se corta si no
  hay tasa cargada, para no mandar precios en cero.

### Lo que sigue, en orden

1. **Foto diaria en `price_snapshots`** + la gráfica del historial: es lo que hace que el usuario
   *crea* que la herramienta trabaja, en vez de tener que creernos.
2. **Aviso de vencimiento** por correo o WhatsApp unos días antes: hoy el usuario solo se entera si
   entra a la app.

### Pendientes que necesitan decisión del negocio

- **Automatizar la confirmación del pago.** Hoy se coteja a mano con `php artisan pagos confirmar`,
  que es lo correcto para las primeras decenas de clientes. Las opciones reales para automatizar,
  de menor a mayor fricción:
  1. **C2P / Botón de Pago** con un banco venezolano (Mercantil, Banesco, BNC). Es la solución de
     verdad —debita solo— pero exige RIF jurídico y trámite bancario: es un paso de negocio, no de
     código.
  2. **Binance Pay**: tiene API y es común entre vendedores digitales venezolanos. Menos trámite.
  3. **Leer las notificaciones del banco** por correo y cotejar la referencia. Barato pero frágil.
  Cualquiera de las tres entra llamando a `SuscripcionService::confirmar()`. Nada del control de
  acceso cambia.
- **Datos de cobro reales.** `PlanSeeder` siembra un Pago Móvil de ejemplo (`V-00000000`). Hay que
  reemplazarlo antes de cobrarle a nadie.
- **Entrar con Google.** El botón está en la interfaz porque el público no recuerda contraseñas,
  pero `laravel/socialite` **todavía no soporta Laravel 13**: la ruta existe y avisa en vez de
  romperse (mismo criterio que IGA con el router y la clave de IA). Revisar cuando publiquen
  compatibilidad.
- **Recuperación de contraseña**: el enlace avisa que no está lista. Falta configurar correo.
- **PWA**: decidido web ligera; falta el manifest y el service worker para que funcione con señal
  intermitente.

---

## 12. Comandos

```bash
php artisan migrate:fresh --seed
php artisan test
./vendor/bin/pint
npm run build
```

```bash
php artisan tasa:sync               # traer la tasa del día a mano
php artisan pagos pendientes        # pagos por cotejar
php artisan pagos confirmar 12      # activar la suscripción
```

**Tests**: contra Postgres (`norte_test`), no sqlite en memoria — las diferencias de dialecto tienen
que salir en las pruebas. Crear la base una vez con `createdb -U postgres -p 5433 norte_test`.

`migrate:fresh --seed` deja un negocio «Bodega Demo» con catálogo de ejemplo y el usuario
`admin@mail.com` / `1234`. El catálogo incluye a propósito productos con costo en dólares **y** en
bolívares: sin los segundos no se ve funcionando la pieza central del producto.

**Una lección que costó un fallo**: al endurecer `users.tenant_id` a NOT NULL no se revisó quién más
escribía usuarios, y la factory y el seeder por defecto de Laravel los creaban sueltos —
`migrate:fresh --seed` reventaba. Pasó desapercibido porque **todos los tests creaban sus usuarios a
mano**, así que la factory nunca se ejercitaba. `ArranqueTest` existe para eso: prueba el camino que
recorre quien clona el repositorio. Un camino que nadie prueba está roto y todavía no lo sabes.

**Ojo con el entorno**: PHP en esta máquina no traía bundle de certificados CA, así que toda
llamada HTTPS saliente fallaba con «SSL certificate problem» — incluida la de la tasa, que es el
latido del producto. Se descargó `cacert.pem` y se apuntaron `curl.cainfo` y `openssl.cafile` en el
`php.ini` (hay respaldo `.bak` al lado). En un servidor nuevo hay que repetirlo.
