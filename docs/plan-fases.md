# PRD ejecutable — Motor de reservas de experiencias

Documento de trabajo para un agente de codificación. Una fase por sesión y por commit. El agente no avanza a la fase siguiente hasta que la verificación de la actual pasa en verde.

Cada fase declara qué casos de uso de `use-cases.md` cubre. Las fases sin esa línea son andamiaje.

**Stack fijado:** PHP 8.3 · Symfony 7.4 · PostgreSQL 17 · Doctrine ORM · PHPUnit 13 · Docker Compose

**Línea de base del repo** — no se parte de un directorio vacío. El esqueleto Symfony 7.4 Flex ya existe, con Doctrine, Messenger, Mailer, Serializer, Validator, PHPUnit 13, PHPStan y PHP CS Fixer. `compose.yaml` solo declara el servicio `database` (`postgres:17-alpine`). Faltan `Dockerfile`, servicios `php` y `nginx`, `Makefile`, deptrac, monolog y el árbol hexagonal. F0 y F1 completan ese esqueleto; no lo recrean ni revierten los paquetes ya instalados.

---

## Reglas permanentes

Aplican a todas las fases. Una violación invalida la fase aunque los tests pasen.

1. `src/*/Domain` no importa `Symfony\`, `Doctrine\`, `Psr\`, ni nada de `src/*/Infrastructure`.
2. `src/*/Application` no importa `Doctrine\` ni `Symfony\Component\HttpFoundation`.
3. `new DateTimeImmutable()` sin argumentos solo puede aparecer en `SystemClock`. En el resto del código, la hora llega por el puerto `Clock`.
4. Los importes son enteros de la unidad mínima de la divisa. `float` está prohibido para dinero.
5. El mapeo de Doctrine vive en XML bajo `config/doctrine/`. Las entidades no llevan atributos de ORM.
6. Una migración ya creada no se edita. Los cambios de esquema van en una migración nueva.
7. No se instalan dependencias que la fase no liste. Las que ya están en `composer.json` al empezar la fase no se reinstalan ni se eliminan.
8. No se crean archivos fuera de la lista `Crea` de la fase.
9. Entre módulos, las dependencias van `Booking → Session → Experience → Shared`. Nunca al revés, y sin ciclos.
10. Constructores de agregados privados, cero setters, referencias entre agregados por identificador. La lógica que abarca dos agregados vive en un servicio de dominio, no en el manejador.
11. `make check` en verde al cerrar cada fase.

---

## Contrato de la API

Congelado desde F0. Ninguna fase lo altera.

| Método | Ruta | Cuerpo | Éxito | Caso de uso |
|---|---|---|---|---|
| POST | `/api/experiences` | `providerId`, `title`, `description`, `timezone` | `201` + `Location` | UC-01 |
| GET | `/api/experiences/{experienceId}` | — | `200` | UC-02 |
| POST | `/api/experiences/{experienceId}/sessions` | `startsAt` (ISO-8601 con offset), `capacity`, `priceAmount`, `priceCurrency` | `201` + `Location` | UC-03 |
| GET | `/api/sessions/{sessionId}` | — | `200` | UC-04 |
| POST | `/api/sessions/{sessionId}/bookings` | `userId`, `seats`, `contactEmail` | `201` + `Location` | UC-05 |
| GET | `/api/bookings/{bookingId}` | — | `200` | UC-06 |
| POST | `/api/bookings/{bookingId}/cancellation` | — | `204` | UC-07 |

**Representaciones** — Experiencia: `id`, `providerId`, `title`, `description`, `timezone`. Sesión: `id`, `experienceId`, `startsAt`, `capacity`, `seatsTaken`, `seatsAvailable`, `price` (`amount`, `currency`). Reserva: `id`, `sessionId`, `userId`, `seats`, `status`, `total` (`amount`, `currency`); el `contactEmail` no se devuelve.

Cada `201` lleva `Location` apuntando a un `GET` que existe y responde `200`. Las rutas son sustantivos, sin verbos. La cancelación es un subrecurso y no un `DELETE` porque repetirla debe fallar.

**Errores** — cuerpo `application/problem+json` con `type`, `title`, `status`, `detail`.

| Origen | HTTP | `type` |
|---|---|---|
| Cuerpo no parseable | 400 | `malformed-json` |
| Media type distinto de `application/json` | 415 | `unsupported-media-type` |
| `InvalidValue` | 422 | `invalid-value` |
| `SessionInThePast` | 422 | `session-in-the-past` |
| `ExperienceNotFound` | 404 | `experience-not-found` |
| `SessionNotFound` | 404 | `session-not-found` |
| `BookingNotFound` | 404 | `booking-not-found` |
| `SessionDayTaken` | 409 | `session-day-taken` |
| `SessionAlreadyStarted` | 409 | `session-already-started` |
| `NotEnoughSeats` | 409 | `not-enough-seats` |
| `BookingAlreadyCancelled` | 409 | `booking-already-cancelled` |
| `CancellationWindowClosed` | 409 | `cancellation-window-closed` |

`SessionInThePast` extiende `InvalidValue`; las tres `*NotFound` extienden `NotFound`; las cinco restantes extienden `Conflict`.

Los dos primeros no son errores de dominio: se resuelven en el borde HTTP, antes de que la petición alcance la capa de aplicación.

---

## Mapa de directorios

Congelado desde F0. Las fases rellenan huecos, no reorganizan.

```
src/
├── Shared/
│   ├── Domain/          Aggregate/ Bus/Event/ Clock/ Exception/ ValueObject/
│   └── Infrastructure/  Bus/Event/ Clock/ Http/ Mailer/ Persistence/Doctrine/
├── Experience/          Domain/ Application/ Infrastructure/
├── Session/             Domain/ Domain/Service/ Application/ Infrastructure/
└── Booking/             Domain/ Domain/Service/ Application/ Infrastructure/
tests/
├── Shared/  Experience/  Session/  Booking/     (Unit/ Application/ Functional/)
└── Concurrency/
config/doctrine/*.orm.xml
migrations/
```

Los puertos (`Clock`, `ExperienceRepository`, `SessionRepository`, `BookingRepository`, `Mailer`) se declaran como interfaces dentro de `Domain`. Sus implementaciones viven en `Infrastructure`.

---

## Non-goals

Fuera de alcance. Si el agente propone cualquiera de estos, se rechaza el cambio.

**Producto** — pagos, pasarelas, reembolsos · autenticación, autorización y sesiones de usuario · alta y gestión de proveedores o usuarios como entidades propias · catálogo, búsqueda o filtrado · listas de espera · modificación de reservas ya creadas · reprogramación o cancelación de sesiones por parte del proveedor · descuentos, cupones y tarifas variables · notificaciones fuera del correo · traducción de mensajes.

**Técnico** — frontend de cualquier tipo · envío real de correo o integración con proveedor SMTP · CQRS con modelos de lectura separados · event sourcing · caché · colas o workers en segundo plano · websockets · despliegue, CI en la nube o infraestructura como código · autenticación de la API · paginación y endpoints de listado · versionado de la API · generación de OpenAPI · claves de idempotencia · patrón outbox · trabajos de reconciliación · métricas y trazas.

**De proceso** — refactorizar código de fases cerradas salvo que la fase actual lo pida por escrito · añadir abstracciones para casos de uso que no estén en el contrato de la API · sustituir librerías ya elegidas.

---

## Fases

### F0 — Contenedores de aplicación
**Depende de:** — · **Duración:** 10 min

**Entrega** — La aplicación arranca dentro de Compose, habla con PostgreSQL y tiene el árbol hexagonal vacío.

**Crea**
- `Dockerfile` con PHP 8.3-fpm, extensiones `pdo_pgsql`, `intl` y Composer.
- En `compose.yaml`, servicios `php` y `nginx` (puerto anfitrión 8080) en la misma red que `database`. No se recrea ni se renombra el servicio `database` (`postgres:17-alpine`).
- El árbol de directorios completo del mapa anterior, con un `.gitkeep` por carpeta vacía. `src/Kernel.php` se conserva. Los directorios Flex `src/Entity`, `src/Repository` y `src/Controller` no forman parte del mapa: no se usa su código.
- `DATABASE_URL` en `.env` y `.env.test` apuntando al host `database` con usuario/contraseña `app`/`app` y `serverVersion=17`.
- En `config/packages/doctrine.yaml`, mapeo XML bajo `config/doctrine/` para los tres contextos. Se retira el mapping Flex `App\Entity` por atributos.
- En `composer.json`, la restricción `"php": ">=8.3"` para coincidir con la imagen.

**Instala** — ninguna dependencia nueva.

**No toca** — `phpunit.dist.xml`, `phpstan.dist.neon`, `.php-cs-fixer.dist.php`, `compose.override.yaml`, los paquetes ya presentes en `composer.json`.

**Acepta cuando**
- `docker compose up -d` deja `php`, `nginx` y `database` en estado `running`.
- `curl -s -o /dev/null -w "%{http_code}" localhost:8080` devuelve un código HTTP, no un error de conexión.
- `docker compose exec php bin/console doctrine:query:sql "SELECT 1"` devuelve una fila.

---

### F1 — Calidad automatizada
**Depende de:** F0 · **Duración:** 10 min

**Entrega** — Un único comando que verifica estilo, tipos, capas y pruebas.

**Crea**
- Suites `unit`, `application`, `functional` y `concurrency` en el `phpunit.dist.xml` ya existente (no se renombra a `phpunit.xml.dist`); `concurrency` excluida de la ejecución por defecto.
- `phpstan.dist.neon` en `level: 9`, analizando `src` y `tests`. No se crea `phpstan.neon`: Flex lo ignora en `.gitignore` como override local.
- `deptrac.yaml` con dos conjuntos de capas: las técnicas (`Domain`, `Application`, `Infrastructure`) por módulo, y las de módulo (`Shared`, `Experience`, `Session`, `Booking`). Expresa como restricciones las reglas permanentes 1, 2 y 9.
- En `.php-cs-fixer.dist.php`, regla `declare_strict_types` además del conjunto `@Symfony` que ya tiene.
- `Makefile` con `up`, `down`, `test`, `stan`, `deptrac`, `cs`, `check` (los cuatro anteriores en cadena) y `concurrency`.

**Instala** — `deptrac/deptrac` como require-dev. Ninguna otra.

**No toca** — `compose.yaml`, `Dockerfile`, `src/`.

**Acepta cuando**
- `make check` termina con código 0 y sin ninguna prueba ejecutada.
- Un archivo de prueba que importe `Doctrine\ORM\EntityManager` desde `src/Experience/Domain` hace fallar `make deptrac`.
- Un archivo de prueba en `src/Experience/Domain` que importe `src/Booking/Domain` hace fallar `make deptrac`. Ambos archivos se borran tras comprobarlo.

---

### F2 — Núcleo compartido: errores, identidad, dinero y reloj
**Depende de:** F1 · **Duración:** 15 min

**Entrega** — Los tipos base que usarán los tres contextos, incluida la jerarquía de errores de dominio de la que dependen todas las validaciones posteriores.

**Crea**
- `src/Shared/Domain/Exception/DomainError.php` (abstracta, con `errorType(): string`) y las hijas `InvalidValue`, `NotFound`, `Conflict`. Cada excepción concreta de los contextos devuelve su propio `errorType()`.
- `src/Shared/Domain/ValueObject/Uuid.php` — envuelve `Symfony\Component\Uid` solo en el método de generación; el resto opera sobre `string`.
- `src/Shared/Domain/ValueObject/Money.php` — `int $amount` en unidades mínimas, `string $currency` de 3 letras, `multiply(int $factor)`, `equals()`. Rechaza importes negativos y divisas mal formadas.
- `src/Shared/Domain/Clock/Clock.php` — interfaz con `now(): DateTimeImmutable`.
- `src/Shared/Infrastructure/Clock/SystemClock.php`.
- `src/Shared/Domain/Aggregate/AggregateRoot.php` — acumula eventos en memoria y los libera con `pullDomainEvents()`.
- `src/Shared/Domain/Bus/Event/DomainEvent.php` — interfaz con `aggregateId()` y `occurredOn()`.
- `tests/Shared/FrozenClock.php` — doble que devuelve siempre el instante que se le inyecta.
- `tests/Shared/Unit/MoneyTest.php`, `tests/Shared/Unit/UuidTest.php`, `tests/Shared/Unit/DomainErrorTest.php`.

**No toca** — configuración de F1, `compose.yaml`.

**Acepta cuando**
- `make check` en verde con al menos 10 pruebas unitarias.
- `Money::multiply(3)` sobre 1250 EUR devuelve 3750 EUR.
- Construir `Money` con importe negativo lanza `InvalidValue`.
- Una hija de `Conflict` devuelve su propio `errorType()` y sigue siendo capturable como `DomainError`.
- `grep -rn "new DateTimeImmutable()" src/ | grep -v SystemClock` no devuelve resultados.

---

### F3 — Borde HTTP: errores y buses
**Depende de:** F2 · **Duración:** 15 min

**Entrega** — Traducción de la jerarquía de errores a HTTP, rechazo de peticiones mal formadas y los dos buses de mensajes.

**Crea**
- `src/Shared/Infrastructure/Http/JsonRequestDecoder.php` — decodifica el cuerpo de las peticiones con contenido. Lanza `MalformedJson` si el JSON no parsea y `UnsupportedMediaType` si el `Content-Type` no es `application/json`. Ambas viven en `src/Shared/Infrastructure/Http/` porque no son errores de dominio.
- `src/Shared/Infrastructure/Http/ProblemJsonExceptionListener.php` — mapea `InvalidValue`, `NotFound` y `Conflict` según la tabla de errores, usando el `errorType()` de la excepción concreta; añade `MalformedJson` a `400` y `UnsupportedMediaType` a `415`. Cualquier otra excepción sale como `500` sin filtrar el mensaje interno.
- Completa el `config/packages/messenger.yaml` ya existente: bus `command.bus` con el middleware `doctrine_transaction`, y bus `event.bus` con `dispatch_after_current_bus`.
- `tests/Shared/Functional/ProblemJsonTest.php` — una ruta de prueba que recibe cuerpo y lanza cada excepción; asevera código, `Content-Type` y forma del cuerpo.

**No toca** — `src/Shared/Domain` completo.

**Acepta cuando**
- `make check` en verde.
- Una `Conflict` produce `409`, `Content-Type: application/problem+json` y un cuerpo con las cuatro claves.
- Una hija concreta de `NotFound` produce `404` con su propio `type`, no con uno genérico.
- Cuerpo con JSON roto → `400` con `type: malformed-json`.
- `Content-Type: text/plain` con cuerpo → `415` con `type: unsupported-media-type`.
- Una `RuntimeException` produce `500` y su mensaje no aparece en la respuesta.

---

### F4 — Dominio Experience
**Depende de:** F2 · **Duración:** 10 min · **Cubre:** UC-01, UC-02

**Entrega** — El agregado `Experience` y su puerto de persistencia.

**Crea**
- `src/Experience/Domain/Experience.php` — identidad, `ProviderId`, `Title`, `Description`, `DateTimeZone`. Constructor privado, método de fábrica `create()`.
- `src/Experience/Domain/ExperienceId.php`, `ProviderId.php`, `Title.php`, `Description.php`.
- `src/Experience/Domain/ExperienceRepository.php` — `save()`, `find(ExperienceId): ?Experience`.
- `src/Experience/Domain/ExperienceNotFound.php` extendiendo `NotFound`, con `errorType()` igual a `experience-not-found`.
- `tests/Experience/Unit/ExperienceTest.php`.

`Title` rechaza cadenas vacías y de más de 150 caracteres. La zona horaria rechaza identificadores que no existan en la base de datos de husos.

**No toca** — `src/Shared`, `src/Session`, `src/Booking`.

**Acepta cuando**
- `make check` en verde.
- `Title` vacío y zona horaria `Mars/Olympus` lanzan `InvalidValue`.
- `ExperienceNotFound` devuelve `experience-not-found` en `errorType()`.

---

### F5 — Aplicación Experience
**Depende de:** F3, F4 · **Duración:** 10 min · **Cubre:** UC-01

**Entrega** — El caso de uso de alta, probado contra un repositorio en memoria.

**Crea**
- `src/Experience/Application/Create/CreateExperienceCommand.php` y `CreateExperienceCommandHandler.php`.
- `tests/Experience/InMemoryExperienceRepository.php`.
- `tests/Experience/Application/CreateExperienceTest.php`.

El manejador genera el identificador y lo devuelve al llamante.

**No toca** — `src/Experience/Domain`, `src/Shared`.

**Acepta cuando**
- `make check` en verde.
- El manejador no importa nada de `Doctrine\` ni de `Symfony\Component\HttpFoundation`, verificado por `make deptrac`.

---

### F6 — Infraestructura Experience
**Depende de:** F5 · **Duración:** 15 min · **Cubre:** UC-01, UC-02

**Entrega** — Persistencia real y los dos primeros endpoints vivos.

**Crea**
- `config/doctrine/Experience.orm.xml`.
- `migrations/VersionXXXXXX_experiences.php` — tabla `experiences` con clave primaria UUID.
- `src/Experience/Infrastructure/Persistence/DoctrineExperienceRepository.php`.
- `src/Experience/Infrastructure/Http/CreateExperienceController.php` y `GetExperienceController.php`, más sus entradas en `config/routes.yaml`.
- `tests/Experience/Functional/ExperienceEndpointsTest.php`.

**No toca** — `src/Experience/Domain`, `src/Experience/Application`, el listener y el decodificador de F3.

**Acepta cuando**
- `make check` en verde.
- `POST /api/experiences` con cuerpo válido devuelve `201`, y un `GET` a la URI de su cabecera `Location` devuelve `200` con las cinco claves de la representación.
- `GET /api/experiences/{id}` con un identificador desconocido → `404` con `type: experience-not-found`.
- Cuerpo con JSON roto → `400`. `Content-Type: text/plain` → `415`. Ambas son regresión de F3.
- Falta `title` en el cuerpo → `422` con `type: invalid-value`.
- `grep -rn "ORM\\\\" src/Experience/Domain` no devuelve resultados.

---

### F7 — Dominio Session
**Depende de:** F4 · **Duración:** 15 min · **Cubre:** UC-03, UC-04, UC-05

**Entrega** — El agregado que custodia el aforo y las tres reglas temporales.

**Crea**
- `src/Session/Domain/Session.php` — identidad, `ExperienceId`, `startsAt`, `Capacity`, `seatsTaken`, `Money $price`. Constructor privado. Métodos `schedule(Clock)`, `reserve(Seats, Clock)`, `release(Seats)`, `seatsAvailable(): Seats`, `hasStarted(Clock)`, `startsWithin(DateInterval, Clock)`, `priceFor(Seats): Money`.
- `src/Session/Domain/SessionId.php`, `Capacity.php`, `Seats.php`. `Seats` es el objeto valor que también usará `Booking`.
- `src/Session/Domain/SessionRepository.php` — `save()`, `find()`, `getForUpdate(SessionId): Session`, `hasSessionOnDay(ExperienceId, DateTimeImmutable $day)`.
- Excepciones `SessionNotFound`, `SessionDayTaken`, `SessionInThePast`, `SessionAlreadyStarted`, `NotEnoughSeats`, cada una con su `errorType()`.
- `tests/Session/Unit/SessionTest.php` usando `FrozenClock`.

`reserve()` lanza `NotEnoughSeats` cuando las plazas pedidas superan las disponibles, y `SessionAlreadyStarted` cuando el instante de inicio quedó atrás. `release()` nunca deja `seatsTaken` por debajo de cero.

**No toca** — `src/Experience`, `src/Shared`, `src/Booking`.

**Acepta cuando**
- `make check` en verde con al menos 10 pruebas nuevas.
- Reservar 3 sobre una sesión de aforo 10 con 8 ocupadas lanza `NotEnoughSeats` y deja el contador en 8.
- Programar una sesión con `startsAt` anterior al reloj congelado lanza `SessionInThePast`, que es capturable como `InvalidValue`.
- `grep -n "function set" src/Session/Domain/Session.php` no devuelve resultados, y su constructor es `private`.

---

### F8 — Servicio de dominio y aplicación Session
**Depende de:** F5, F7 · **Duración:** 15 min · **Cubre:** UC-03

**Entrega** — La regla de un día, una sesión encapsulada en un servicio de dominio, y el caso de uso que lo invoca.

**Crea**
- `src/Session/Domain/Service/SessionScheduler.php` — recibe `SessionRepository` y `Clock`. Su método `schedule(Experience, DateTimeImmutable $startsAt, Capacity, Money): Session` convierte `startsAt` a la zona horaria de la experiencia, comprueba que no haya sesión ese día civil y devuelve la sesión programada.
- `src/Session/Application/Create/CreateSessionCommand.php` y `CreateSessionCommandHandler.php` — carga la experiencia, delega en el planificador y guarda. Sin condicionales ni aritmética.
- `tests/Session/InMemorySessionRepository.php` — `getForUpdate()` se comporta como `find()`.
- `tests/Session/Unit/SessionSchedulerTest.php` y `tests/Session/Application/CreateSessionTest.php`.

**No toca** — `src/Session/Domain/Session.php` y los objetos valor de F7, `src/Experience`.

**Acepta cuando**
- `make check` en verde.
- Dos sesiones el mismo día civil de la experiencia → la segunda lanza `SessionDayTaken`.
- Dos sesiones separadas por 3 horas que caen en días civiles distintos en la zona de la experiencia → ambas se crean.
- Experiencia inexistente → `ExperienceNotFound`.
- `CreateSessionCommandHandler` no contiene `if`, `>`, `<` ni operadores aritméticos.

---

### F9 — Infraestructura Session
**Depende de:** F6, F8 · **Duración:** 20 min · **Cubre:** UC-03, UC-04

**Entrega** — Persistencia con las dos garantías a nivel de esquema, la traducción de la violación de unicidad y los endpoints de sesión.

**Crea**
- `config/doctrine/Session.orm.xml`.
- `migrations/VersionXXXXXX_sessions.php` — tabla `sessions` con columna generada `session_day` (`DATE`), índice único sobre `(experience_id, session_day)`, y `CHECK (seats_taken >= 0 AND seats_taken <= capacity)`.
- `src/Session/Infrastructure/Persistence/DoctrineSessionRepository.php` — `getForUpdate()` usa `LockMode::PESSIMISTIC_WRITE`. `save()` captura la violación del índice único y la relanza como `SessionDayTaken`, de modo que la carrera entre dos altas simultáneas sale como `409` y nunca como `500`.
- `src/Session/Infrastructure/Http/CreateSessionController.php` y `GetSessionController.php`, más sus rutas.
- `tests/Session/Functional/SessionEndpointsTest.php`.

La comprobación del `SessionScheduler` y el índice único no son redundantes: la primera produce el mensaje legible en el caso normal, el segundo cierra la ventana entre la comprobación y el `INSERT`.

**No toca** — `src/Session/Domain`, `src/Session/Application`, `migrations/VersionXXXXXX_experiences.php`.

**Acepta cuando**
- `make check` en verde.
- `POST /api/experiences/{id}/sessions` válido → `201` con `Location`, y un `GET` a esa URI devuelve `200`.
- Segunda sesión el mismo día → `409` con `type: session-day-taken`.
- Fecha pasada → `422` con `type: session-in-the-past`.
- `GET /api/sessions/{id}` devuelve las siete claves del contrato con `seatsTaken` a 0; con identificador desconocido → `404` con `type: session-not-found`.
- Un `INSERT` directo en SQL que duplique `(experience_id, session_day)` es rechazado por la base de datos.
- Dos peticiones concurrentes de creación para el mismo día devuelven un `201` y un `409`, ninguna `500`.

---

### F10 — Dominio Booking
**Depende de:** F7 · **Duración:** 15 min · **Cubre:** UC-05, UC-06, UC-07

**Entrega** — El agregado de reserva con su máquina de estados y sus eventos.

**Crea**
- `src/Booking/Domain/Booking.php` — identidad, `SessionId`, `UserId`, `Seats`, `Money $total`, `BookingStatus`, `ContactEmail`. Constructor privado, fábrica `confirm()` y método `cancel(DateTimeImmutable $sessionStartsAt, Clock)`.
- `src/Booking/Domain/BookingStatus.php` como `enum` con los casos `Confirmed` y `Cancelled`.
- `src/Booking/Domain/BookingId.php`, `UserId.php`, `ContactEmail.php`. Las plazas usan `Session\Domain\Seats`, no se duplica el objeto valor.
- `src/Booking/Domain/BookingRepository.php` — `save()`, `find()`.
- Excepciones `BookingNotFound`, `BookingAlreadyCancelled`, `CancellationWindowClosed`, cada una con su `errorType()`.
- Eventos `BookingWasConfirmed` y `BookingWasCancelled`, ambos con `contactEmail` en la carga.
- `tests/Booking/Unit/BookingTest.php`.

La ventana de cancelación es una constante del dominio fijada en 24 horas.

**No toca** — `src/Session`, `src/Experience`, `src/Shared`.

**Acepta cuando**
- `make check` en verde con al menos 8 pruebas nuevas.
- `confirm()` con 4 plazas a 1250 EUR deja `total` en 5000 EUR y registra un `BookingWasConfirmed`.
- Cancelar dos veces → la segunda lanza `BookingAlreadyCancelled` y no registra un segundo evento.
- Cancelar a 23 h 59 min del inicio → `CancellationWindowClosed`.
- `Seats` a 0 o negativo → `InvalidValue`.

---

### F11 — Servicio de dominio y aplicación Booking
**Depende de:** F8, F10 · **Duración:** 15 min · **Cubre:** UC-05, UC-07

**Entrega** — Reserva y cancelación coordinando los dos agregados.

**Crea**
- `src/Booking/Domain/Service/SeatReservation.php` — único lugar donde se coordinan los dos agregados. `reserve(Session, UserId, Seats, ContactEmail, Clock): Booking` invoca `Session::reserve()`, obtiene el importe con `Session::priceFor()` y devuelve la reserva confirmada. `cancel(Booking, Session, Clock): void` cancela y devuelve las plazas con `Session::release()`.
- `src/Booking/Application/Book/BookSeatsCommand.php` y `BookSeatsCommandHandler.php` — obtiene la sesión con `getForUpdate()`, delega en el servicio de dominio y guarda ambos agregados.
- `src/Booking/Application/Cancel/CancelBookingCommand.php` y `CancelBookingCommandHandler.php` — misma forma.
- `tests/Booking/InMemoryBookingRepository.php`.
- `tests/Booking/Unit/SeatReservationTest.php`, `tests/Booking/Application/BookSeatsTest.php` y `CancelBookingTest.php`.

**No toca** — `src/Booking/Domain/Booking.php` y los objetos valor de F10, `src/Session/Domain`, `src/Session/Application`.

**Acepta cuando**
- `make check` en verde.
- Reservar 3 sobre 10 libres deja la sesión con `seatsTaken` a 3 y la reserva en `confirmed`.
- Reservar 3 sobre 2 libres lanza `NotEnoughSeats` y deja la sesión intacta.
- Cancelar devuelve las plazas exactas al contador de la sesión.
- Cancelar una reserva ya cancelada no altera el contador.
- Ninguno de los dos manejadores contiene `if`, comparaciones ni operadores aritméticos; toda la decisión está en `SeatReservation` o dentro de los agregados.

---

### F12 — Infraestructura Booking
**Depende de:** F9, F11 · **Duración:** 15 min · **Cubre:** UC-05, UC-06, UC-07

**Entrega** — Los tres endpoints de reserva sobre transacción real con bloqueo.

**Crea**
- `config/doctrine/Booking.orm.xml`.
- `migrations/VersionXXXXXX_bookings.php` — tabla `bookings` con índice sobre `session_id` y `status` como cadena.
- `src/Booking/Infrastructure/Persistence/DoctrineBookingRepository.php`.
- `src/Booking/Infrastructure/Http/BookSeatsController.php`, `CancelBookingController.php` y `GetBookingController.php`, más sus rutas.
- `tests/Booking/Functional/BookingEndpointsTest.php`.

Los controladores despachan por `command.bus`, de modo que el middleware `doctrine_transaction` de F3 envuelve el manejador completo.

**No toca** — `src/Booking/Domain`, `src/Booking/Application`, migraciones anteriores.

**Acepta cuando**
- `make check` en verde.
- `POST /api/sessions/{id}/bookings` válido → `201`; un `GET` a la URI de su `Location` devuelve `200` con `status: confirmed`, y un `GET` de la sesión muestra el contador actualizado.
- La representación de la reserva no contiene `contactEmail`.
- Plazas insuficientes → `409` con `type: not-enough-seats`.
- Sesión ya empezada → `409` con `type: session-already-started`.
- `POST /api/bookings/{id}/cancellation` válido → `204`; segunda llamada → `409` con `type: booking-already-cancelled`.
- Sesión inexistente al reservar → `404` con `type: session-not-found`. Reserva inexistente al consultar o cancelar → `404` con `type: booking-not-found`.

---

### F13 — Prueba de concurrencia
**Depende de:** F12 · **Duración:** 10 min · **Cubre:** UC-05 (requisito no funcional)

**Entrega** — Evidencia ejecutable de que el aforo aguanta el pico.

**Crea**
- `tests/Concurrency/SeatContentionTest.php` — prepara vía API una experiencia y una sesión de aforo 10, lanza 50 peticiones simultáneas de 1 plaza con `curl_multi` contra la aplicación en ejecución, y asevera el reparto.
- Objetivo `make concurrency` en el `Makefile`, apuntando a la suite excluida en F1.

Esta prueba corre contra el contenedor levantado y su base de datos, no contra dobles.

**No toca** — todo `src/`. Si la prueba falla, se corrige en una fase nueva, no aquí.

**Acepta cuando**
- `make concurrency` devuelve exactamente 10 respuestas `201` y 40 respuestas `409`.
- El `GET` final de la sesión muestra `seatsTaken` igual a 10 y `seatsAvailable` igual a 0.
- `SELECT count(*) FROM bookings WHERE session_id = ? AND status = 'confirmed'` devuelve 10.
- Ninguna respuesta es `500`.

---

### F14 — Eventos de dominio y correo
**Depende de:** F12 · **Duración:** 15 min · **Cubre:** UC-08, UC-09

**Entrega** — La notificación como efecto posterior a la transacción.

**Crea**
- `src/Shared/Domain/Mailer.php` — puerto con `send(string $to, string $subject, string $body)`.
- `src/Shared/Infrastructure/Mailer/LoggerMailer.php` — escribe por el logger de Symfony; no envía correo real. El `symfony/mailer` ya instalado permanece con `MAILER_DSN=null://null`.
- `src/Shared/Infrastructure/Bus/Event/DoctrineDomainEventPublisher.php` — recoge los eventos de los agregados guardados y los despacha por `event.bus`.
- `src/Booking/Infrastructure/Notification/SendBookingConfirmationEmail.php` y `SendBookingCancellationEmail.php`.
- `tests/Booking/SpyMailer.php`.
- `tests/Booking/Functional/BookingNotificationTest.php`.

**Instala** — `symfony/monolog-bundle`, para que exista el servicio `logger` que usa `LoggerMailer`.

**No toca** — `src/Booking/Domain`, `src/Booking/Application`, `src/Session`, los controladores de F12.

**Acepta cuando**
- `make check` en verde.
- Una reserva con éxito produce exactamente una llamada al `Mailer`, dirigida al `contactEmail` de la petición.
- Una reserva que falla por aforo produce cero llamadas al `Mailer`.
- Una segunda cancelación rechazada produce cero llamadas al `Mailer`.
- Forzar un fallo en el guardado tras invocar `reserve()` deja cero llamadas al `Mailer` y cero filas nuevas en `bookings`.
- `grep -rn "Mailer" src/Booking/Application` no devuelve resultados.

---

## Ciclo de trabajo

Una fase por conversación con el agente. El mensaje de arranque incluye las reglas permanentes, el contrato de la API y el bloque de la fase, nada más. Al terminar, `make check`, revisión humana del diff contra la lista `No toca`, y commit con el identificador de la fase como prefijo del mensaje.

Si una fase falla la verificación dos veces seguidas, se parte en dos en lugar de reintentar una tercera.