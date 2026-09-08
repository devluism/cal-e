import { IsotipoNorte } from '@/Components/Marca/LogoNorte';

/**
 * Panel de marca de las pantallas de acceso.
 *
 * La referencia que inspiró esto usaba una foto con degradado. Acá el arte es **generado**,
 * no una imagen: pesa unos kilobytes en vez de cientos, se ve nítido en cualquier pantalla,
 * y se re-tematiza solo si cambia la marca. Con el público de Norte —teléfono, datos
 * caros, señal irregular— cargar una foto de fondo en la pantalla de entrada es cobrarle al
 * usuario megas antes de que el producto le haya dado nada.
 *
 * El motivo es la rosa de los vientos: agujas rotadas alrededor de un centro, cada vez más
 * tenues. Dice "norte" sin escribirlo.
 */
export default function PanelMarca() {
    return (
        <div className="relative hidden overflow-hidden rounded-3xl bg-noche-950 lg:flex lg:flex-col lg:justify-between">
            {/* Resplandor cálido: la luz viene del norte de la brújula. */}
            <div
                aria-hidden
                className="pointer-events-none absolute -left-1/4 -top-1/3 size-[46rem] rounded-full opacity-60 blur-3xl"
                style={{
                    background:
                        'radial-gradient(circle, rgba(247,180,39,0.45) 0%, rgba(213,109,9,0.18) 40%, transparent 70%)',
                }}
            />
            <div
                aria-hidden
                className="pointer-events-none absolute -bottom-1/4 -right-1/4 size-[38rem] rounded-full opacity-50 blur-3xl"
                style={{
                    background:
                        'radial-gradient(circle, rgba(74,106,168,0.5) 0%, rgba(20,28,46,0.2) 55%, transparent 75%)',
                }}
            />

            {/* Rosa de los vientos: la aguja norte marcada y las otras siete apenas
                insinuadas. Los anillos son lo que la hace leerse como brújula y no como
                estrella — sin ellos el ojo ve un sol. */}
            <div aria-hidden className="pointer-events-none absolute inset-0 grid place-items-center">
                <div className="relative size-[30rem]">
                    <div className="absolute inset-[12%] rounded-full border border-norte-400/10" />
                    <div className="absolute inset-[26%] rounded-full border border-norte-400/[0.07]" />

                    {[...Array(8)].map((_, i) => (
                        <IsotipoNorte
                            key={i}
                            className="absolute inset-0 size-full text-norte-400"
                            style={{
                                transform: `rotate(${i * 45}deg)`,
                                // La punta que señala se distingue; las demás son contexto.
                                opacity: i === 0 ? 0.16 : 0.028,
                            }}
                        />
                    ))}
                </div>
            </div>

            <div className="relative z-10 p-10">
                <span className="inline-flex items-center gap-2 text-white">
                    <IsotipoNorte className="size-8 text-primary" />
                    <span className="text-xl font-semibold tracking-tight">Norte</span>
                </span>
            </div>

            <div className="relative z-10 p-10">
                <h2 className="max-w-md text-4xl font-semibold leading-tight tracking-tight text-white">
                    Tus precios,
                    <br />
                    siempre al día.
                </h2>

                <p className="mt-4 max-w-md text-pretty text-white/70">
                    La tasa se mueve todos los días. Tus precios también deberían. Norte los recalcula
                    solo y te deja la lista lista para mandar por WhatsApp.
                </p>

                <dl className="mt-10 flex flex-wrap gap-x-10 gap-y-4">
                    {[
                        ['Sin abrir la calculadora', 'La tasa entra sola cada mañana'],
                        ['En 10 segundos', 'Copias tu lista y la mandas'],
                    ].map(([titulo, detalle]) => (
                        <div key={titulo}>
                            <dt className="text-sm font-medium text-primary">{titulo}</dt>
                            <dd className="text-sm text-white/60">{detalle}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}
