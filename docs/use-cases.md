# Casos de uso — Motor de reservas de experiencias (v1)

Especificación de los casos de uso representados en `casos-de-uso.puml`. Alineada con el contrato de API congelado en `plan-fases.md`; ninguna fase lo altera.

Los identificadores se han renumerado al incorporar las dos lecturas nuevas, agrupándolos por contexto: experiencia, sesión, reserva y notificaciones. Correspondencia con la numeración anterior: UC-01 → UC-01 · UC-02 → UC-03 · UC-03 → UC-05 · UC-04 → UC-07 · UC-05 → UC-04 · UC-06 → UC-08 · UC-07 → UC-09. UC-02 y UC-06 son nuevos.

---

## Actores

| Actor | Tipo | Descripción |
|---|---|---|
| Cliente | Primario | Consulta el catálogo y la disponibilidad, compra plazas, consulta y cancela sus reservas. |
| Proveedor | Primario | Publica experiencias y programa sesiones. Consulta su propia oferta. |
| Servicio de correo | Secundario | Sistema externo invocado por la plataforma. En v1, adaptador simulado con la firma del proveedor real. |

UC-02 tiene dos actores primarios: el Cliente consulta la experiencia para saber qué está comprando y el Proveedor para verificar la oferta que acaba de dar de alta.

La v1 no implementa autenticación. La distinción entre Cliente y Proveedor es lógica: `providerId` y `userId` viajan en el cuerpo de la petición y se confían. Riesgo asumido y bloqueante para producción.

---

## Índice

| ID | Caso de uso | Actor | Endpoint | Historias | Criterios |
|---|---|---|---|---|---|
| UC-01 | Registrar experiencia | Proveedor | `POST /api/experiences` | US-01 | AC-01 |
| UC-02 | Consultar una experiencia | Cliente, Proveedor | `GET /api/experiences/{experienceId}` | — | — |
| UC-03 | Programar sesión de una experiencia | Proveedor | `POST /api/experiences/{experienceId}/sessions` | US-02, US-03, US-04 | AC-02, AC-03, AC-04 |
| UC-04 | Consultar disponibilidad de una sesión | Cliente | `GET /api/sessions/{sessionId}` | — | — |
| UC-05 | Reservar plazas en una sesión | Cliente | `POST /api/sessions/{sessionId}/bookings` | US-05, US-07, US-08, US-11 | AC-05, AC-06, AC-07, AC-08 |
| UC-06 | Consultar una reserva | Cliente | `GET /api/bookings/{bookingId}` | US-08, US-12 | — |
| UC-07 | Cancelar una reserva | Cliente | `POST /api/bookings/{bookingId}/cancellation` | US-06, US-10, US-12, US-13 | AC-09, AC-10, AC-11 |
| UC-08 | Notificar reserva confirmada | — (incluido por UC-05) | — | US-09 | AC-12, AC-13 |
| UC-09 | Notificar reserva cancelada | — (incluido por UC-07) | — | US-09 | AC-12 |

---

## Extensiones transversales

Aplican a todos los casos de uso con endpoint y no se repiten en cada ficha. Todo error se devuelve en `application/problem+json` con `type`, `title`, `status` y `detail`.

| Condición | Resultado |
|---|---|
| Cuerpo presente y no parseable como JSON | `400` · `malformed-json` |
| `Content-Type` distinto de `application/json` en una petición con cuerpo | `415` · `unsupported-media-type` |
| Cualquier fallo no previsto | `500` sin exponer el mensaje interno |

Se evalúan antes de que la petición alcance la capa de aplicación, así que preceden a cualquier extensión de las fichas siguientes.

---

## UC-01 — Registrar experiencia

**Actor primario** · Proveedor
**Objetivo** · Publicar una oferta en la plataforma para poder colgar sesiones de ella.
**Endpoint** · `POST /api/experiences` → `201` + `Location`
**Entrada** · `providerId`, `title`, `description`, `timezone`

**Precondiciones** — ninguna.

**Flujo principal**

1. El proveedor envía identificador de proveedor, título, descripción y zona horaria.
2. El sistema valida los valores recibidos.
3. El sistema crea la experiencia con identidad propia y la persiste.
4. El sistema responde `201` con una cabecera `Location` que apunta a UC-02.

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 2a | Título vacío o de más de 150 caracteres | `422` · `invalid-value` |
| 2b | Zona horaria inexistente en la base de husos | `422` · `invalid-value` |

**Postcondiciones** — existe una experiencia con su zona horaria fijada. Ningún otro agregado se ve afectado.

**Reglas aplicadas** — la zona horaria se fija en el alta y es la referencia con la que se evalúan «mismo día» (UC-03) y «24 horas antes» (UC-07).

---

## UC-02 — Consultar una experiencia

**Actores primarios** · Cliente, Proveedor
**Objetivo** · Ver los datos de una oferta publicada.
**Endpoint** · `GET /api/experiences/{experienceId}` → `200`

**Precondiciones** — ninguna.

**Flujo principal**

1. El actor solicita una experiencia por su identificador.
2. El sistema responde con `id`, `providerId`, `title`, `description` y `timezone`.

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 1a | La experiencia no existe | `404` · `experience-not-found` |

**Notas** — es el destino del `Location` que devuelve UC-01. No lista las sesiones de la experiencia: el catálogo y los endpoints de listado quedan fuera de alcance.

---

## UC-03 — Programar sesión de una experiencia

**Actor primario** · Proveedor
**Objetivo** · Poner plazas a la venta en una fecha concreta.
**Endpoint** · `POST /api/experiences/{experienceId}/sessions` → `201` + `Location`
**Entrada** · `startsAt` (ISO-8601 con offset), `capacity`, `priceAmount`, `priceCurrency`

**Precondiciones** — la experiencia existe.

**Flujo principal**

1. El proveedor envía fecha de inicio, aforo y precio.
2. El sistema recupera la experiencia y su zona horaria.
3. El sistema comprueba que la fecha de inicio es posterior al instante actual.
4. El sistema comprueba que la experiencia no tiene ya otra sesión ese mismo día civil.
5. El sistema crea la sesión con el contador de ocupación a cero y la persiste.
6. El sistema responde `201` con una cabecera `Location` que apunta a UC-04.

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 1a | Aforo menor o igual a cero, importe negativo o divisa no válida | `422` · `invalid-value` |
| 2a | La experiencia no existe | `404` · `experience-not-found` |
| 3a | Fecha de inicio anterior al instante actual | `422` · `session-in-the-past` |
| 4a | Ya hay una sesión de esa experiencia ese día | `409` · `session-day-taken`; no se persiste nada |
| 4b | Dos altas simultáneas del mismo día superan la comprobación de dominio | El índice único sobre `(experienceId, día local)` rechaza la segunda; la persistencia traduce la violación a `session-day-taken` y la respuesta es `409`, nunca `500` |

**Postcondiciones** — la sesión queda disponible con plazas libres iguales al aforo.

**Reglas aplicadas** — el día civil se evalúa en la zona horaria de la experiencia, no en UTC ni en la del servidor. La unicidad vive en dos capas: el servicio de dominio produce el mensaje legible y el índice único la sostiene bajo concurrencia.

---

## UC-04 — Consultar disponibilidad de una sesión

**Actor primario** · Cliente
**Objetivo** · Saber cuántas plazas quedan y a qué precio antes de intentar reservar.
**Endpoint** · `GET /api/sessions/{sessionId}` → `200`

**Precondiciones** — ninguna.

**Flujo principal**

1. El cliente solicita una sesión por su identificador.
2. El sistema responde con `id`, `experienceId`, `startsAt`, `capacity`, `seatsTaken`, `seatsAvailable` y `price` (`amount`, `currency`).

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 1a | La sesión no existe | `404` · `session-not-found` |

**Notas** — es el destino del `Location` que devuelve UC-03. Además de su valor para el cliente, permite que las pruebas funcionales y la de concurrencia aseveren el estado del contador.

---

## UC-05 — Reservar plazas en una sesión

**Actor primario** · Cliente
**Objetivo** · Asegurar un número de plazas para todo el grupo en una sola operación.
**Endpoint** · `POST /api/sessions/{sessionId}/bookings` → `201` + `Location`
**Entrada** · `userId`, `seats`, `contactEmail`
**Incluye** · UC-08

**Precondiciones** — la sesión existe y su fecha de inicio no ha pasado.

**Flujo principal**

1. El cliente envía identificador de usuario, número de plazas y correo de contacto.
2. El sistema abre una transacción y obtiene la sesión con bloqueo pesimista sobre su fila.
3. El sistema comprueba que la sesión no ha empezado.
4. El sistema comprueba que las plazas libres bastan para lo solicitado.
5. El sistema incrementa el contador de ocupación de la sesión.
6. El sistema calcula el importe total y lo congela en la reserva.
7. El sistema crea la reserva en estado `confirmed` y registra el evento de confirmación.
8. El sistema confirma la transacción.
9. El sistema responde `201` con una cabecera `Location` que apunta a UC-06.
10. Despachado el evento tras el commit, se ejecuta UC-08.

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 1a | Plazas menores o iguales a cero | `422` · `invalid-value` |
| 1b | Correo de contacto con formato no válido | `422` · `invalid-value` |
| 2a | La sesión no existe | `404` · `session-not-found` |
| 3a | La sesión ya ha empezado | `409` · `session-already-started` |
| 4a | Plazas insuficientes | `409` · `not-enough-seats`; el contador de ocupación no varía |
| 8a | La transacción falla | Se revierte por completo: cero cambios persistidos y cero notificaciones |

**Postcondiciones** — el contador de ocupación sube exactamente en el número de plazas reservadas; existe una reserva `confirmed` con su importe total congelado; se emite una única notificación.

**Reglas aplicadas** — el contador nunca supera el aforo ni baja de cero, respaldado por una restricción en base de datos que rechaza toda escritura por encima del aforo. El importe se congela en la reserva: un cambio posterior de precio no altera el comprobante ya emitido. La coordinación entre los agregados de sesión y reserva vive en un servicio de dominio, no en el manejador.

**Requisitos no funcionales** — p95 por debajo de 300 ms con 200 peticiones concurrentes sobre la misma sesión. 50 peticiones simultáneas de una plaza sobre una sesión de aforo 10 dan 10 confirmadas y 40 rechazadas con `409`, quedando el contador en 10.

---

## UC-06 — Consultar una reserva

**Actor primario** · Cliente
**Objetivo** · Tener a mano el comprobante: qué se reservó, en qué estado está y cuánto costó.
**Endpoint** · `GET /api/bookings/{bookingId}` → `200`

**Precondiciones** — ninguna.

**Flujo principal**

1. El cliente solicita una reserva por su identificador.
2. El sistema responde con `id`, `sessionId`, `userId`, `seats`, `status` y `total` (`amount`, `currency`).

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 1a | La reserva no existe | `404` · `booking-not-found` |

**Postcondiciones** — ninguna; la consulta no altera el estado.

**Reglas aplicadas** — el `contactEmail` se persiste con la reserva pero no se devuelve en la representación. Al no haber autenticación, exponerlo daría acceso a un dato personal a cualquiera con el identificador.

**Notas** — es el destino del `Location` que devuelve UC-05 y lo que cubre US-12: el estado de la reserva se resuelve con un solo dato, sin reconstruir el histórico a mano.

---

## UC-07 — Cancelar una reserva

**Actor primario** · Cliente
**Objetivo** · Liberarse del compromiso dentro del plazo permitido.
**Endpoint** · `POST /api/bookings/{bookingId}/cancellation` → `204`
**Entrada** · sin cuerpo
**Incluye** · UC-09

**Precondiciones** — la reserva existe y está en estado `confirmed`.

**Flujo principal**

1. El cliente solicita la cancelación de su reserva.
2. El sistema abre una transacción y obtiene la sesión con bloqueo pesimista sobre su fila.
3. El sistema comprueba que la reserva sigue en `confirmed`.
4. El sistema comprueba que faltan más de 24 horas para el inicio de la sesión.
5. El sistema transiciona la reserva a `cancelled` y registra el evento de cancelación.
6. El sistema devuelve las plazas al contador de ocupación de la sesión.
7. El sistema confirma la transacción y responde `204`.
8. Despachado el evento tras el commit, se ejecuta UC-09.

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 2a | La reserva no existe | `404` · `booking-not-found` |
| 3a | La reserva ya estaba cancelada | `409` · `booking-already-cancelled`; el estado sigue siendo `cancelled`, las plazas no se devuelven dos veces y no se emite un segundo correo |
| 4a | Faltan 24 horas o menos para el inicio | `409` · `cancellation-window-closed` |

**Postcondiciones** — la reserva queda en `cancelled` y sus plazas vuelven a estar a la venta en el mismo instante; se emite una única notificación.

**Reglas aplicadas** — la cancelación es un subrecurso de la reserva y no un `DELETE`, precisamente porque repetirla debe fallar: el histórico se conserva y la segunda llamada devuelve conflicto explícito, no un `204` idempotente. La ventana de 24 horas es una constante del dominio y en v1 es la misma para todo el catálogo.

---

## UC-08 — Notificar reserva confirmada

**Actor secundario** · Servicio de correo
**Incluido por** · UC-05
**Disparador** · evento de confirmación despachado después del commit

**Flujo principal**

1. El sistema recibe el evento con el correo de contacto de la reserva.
2. El sistema compone el comprobante con los datos de la reserva.
3. El sistema invoca el puerto de correo.

**Extensiones**

| # | Condición | Resultado |
|---|---|---|
| 1a | La transacción de UC-05 no llegó a confirmarse | El evento no se despacha; cero notificaciones |
| 3a | El proveedor de correo falla | No revierte la reserva. En v1 se registra el fallo; el reintento es deuda declarada |

**Postcondiciones** — exactamente una notificación dirigida al correo de contacto recibido en la petición.

**Reglas aplicadas** — el correo se emite desde un evento de dominio despachado tras confirmar la transacción, nunca dentro del caso de uso. La capa de aplicación no conoce el puerto de correo.

---

## UC-09 — Notificar reserva cancelada

**Actor secundario** · Servicio de correo
**Incluido por** · UC-07
**Disparador** · evento de cancelación despachado después del commit

**Flujo principal**

1. El sistema recibe el evento con el correo de contacto de la reserva.
2. El sistema compone el aviso de cancelación, incluyendo de forma explícita que la v1 no contempla reembolso.
3. El sistema invoca el puerto de correo.

**Extensiones** — las mismas que UC-08. Una segunda cancelación rechazada no emite ningún correo.

**Postcondiciones** — exactamente una notificación por cancelación efectiva.

---

## Trazabilidad

### Historias de usuario

| Historia | Casos de uso |
|---|---|
| US-01 Registrar experiencia | UC-01 |
| US-02 Crear sesiones | UC-03 |
| US-03 Una sesión por día | UC-03 |
| US-04 Sin fechas pasadas | UC-03 |
| US-05 Nunca sobrevender | UC-05 |
| US-06 Plazo mínimo de cancelación | UC-07 |
| US-07 Varias plazas en una operación | UC-05 |
| US-08 Ver el importe total | UC-05, UC-06 |
| US-09 Correo al reservar y al cancelar | UC-08, UC-09 |
| US-10 Cancelar una reserva | UC-07 |
| US-11 Motivo concreto del rechazo | UC-05 y UC-07 (extensiones) |
| US-12 Estado inequívoco, cancelación no repetible | UC-06, UC-07 |
| US-13 Plazas de vuelta al inventario al instante | UC-07 |

### Fases de implementación

| Caso de uso | Fases |
|---|---|
| UC-01 | F4 · F5 · F6 |
| UC-02 | F4 · F6 |
| UC-03 | F7 · F8 · F9 |
| UC-04 | F7 · F9 |
| UC-05 | F7 · F10 · F11 · F12 · F13 (requisito no funcional) |
| UC-06 | F10 · F12 |
| UC-07 | F10 · F11 · F12 |
| UC-08, UC-09 | F14 |

F0 a F3 son andamiaje: contenedores, verificación automatizada, núcleo compartido y traducción de errores a HTTP. Sostienen las extensiones transversales de todos los casos de uso pero no implementan ninguno.

---

## Fuera de alcance en v1

Autenticación y autorización · pagos y reembolsos · alta y gestión de proveedores y usuarios como entidades · catálogo, búsqueda y filtrado · listas de espera · modificación de reservas · reprogramación o cancelación de sesiones por el proveedor · descuentos y tarifas variables · notificaciones fuera del correo · internacionalización · endpoints de listado y paginación · claves de idempotencia · patrón outbox.

**Deuda declarada con impacto sobre estos casos de uso**

- Sin autenticación, cualquiera con el identificador puede ejecutar UC-06 y UC-07 sobre la reserva de otro. Por eso el `contactEmail` no aparece en la representación.
- Sin clave de idempotencia, un doble clic o un reintento de red sobre UC-05 crea dos reservas.
- Sin patrón outbox, un fallo del proveedor de correo pierde la notificación de UC-08 o UC-09 de forma silenciosa.
- UC-02 no expone las sesiones de la experiencia, así que no hay forma de descubrir un `sessionId` desde la API. En v1 se obtiene del `Location` que devuelve UC-03.