# PRD ejecutable — Motor de reservas de experiencias

Documento de trabajo para un agente de codificación. Una fase por sesión y por commit. El agente no avanza a la fase siguiente hasta que la verificación de la actual pasa en verde.

**Stack fijado:** PHP 8.3 · Symfony 7 · PostgreSQL 16 · Doctrine ORM · PHPUnit 11 · Docker Compose

---

## Reglas permanentes

Aplican a todas las fases. Una violación invalida la fase aunque los tests pasen.

1. `src/*/Domain` no importa `Symfony\`, `Doctrine\`, `Psr\`, ni nada de `src/*/Infrastructure`.
2. `src/*/Application` no importa `Doctrine\` ni `Symfony\Component\HttpFoundation`.
3. `new DateTimeImmutable()` sin argumentos solo puede aparecer en `SystemClock`. En el resto del código, la hora llega por el puerto `Clock`.
4. Los importes son enteros de la unidad mínima de la divisa. `float` está prohibido para dinero.
5. El mapeo de Doctrine vive en XML bajo `config/doctrine/`. Las entidades no llevan atributos de ORM.
6. Una migración ya creada no se edita. Los cambios de esquema van en una migración nueva.
7. No se instalan dependencias que la fase no liste.
8. No se crean archivos fuera de la lista `Crea` de la fase.
9. `make check` en verde al cerrar cada fase.

---

## Contrato de la API

Congelado desde F0. Ninguna fase lo altera.

| Método | Ruta | Cuerpo | Éxito |
|---|---|---|---|
| POST | `/api/experiences` | `providerId`, `title`, `description`, `timezone` | `201` + `Location` |
| POST | `/api/experiences/{experienceId}/sessions` | `startsAt` (ISO-8601 con offset), `capacity`, `priceAmount`, `priceCurrency` | `201` + `Location` |
| POST | `/api/sessions/{sessionId}/bookings` | `userId`, `seats`, `contactEmail` | `201` + `Location` |
| POST | `/api/bookings/{bookingId}/cancellation` | — | `204` |
| GET | `/api/sessions/{sessionId}` | — | `200` |

`GET /api/sessions/{id}` devuelve `id`, `experienceId`, `startsAt`, `capacity`, `seatsTaken`, `seatsAvailable`, `price`. Es el único endpoint de lectura y existe para que las pruebas funcionales y de concurrencia puedan aseverar el estado.

**Errores** — cuerpo `application/problem+json` con `type`, `title`, `status`, `detail`.

| Excepción de dominio | HTTP | `type` |
|---|---|---|
| `InvalidValue` | 422 | `invalid-value` |
| `NotFound` | 404 | `not-found` |
| `Conflict` → `SessionDayTaken` | 409 | `session-day-taken` |
| `Conflict` → `SessionAlreadyStarted` | 409 | `session-already-started` |
| `Conflict` → `NotEnoughSeats` | 409 | `not-enough-seats` |
| `Conflict` → `BookingAlreadyCancelled` | 409 | `booking-already-cancelled` |
| `Conflict` → `CancellationWindowClosed` | 409 | `cancellation-window-closed` |

---

## Mapa de directorios

Congelado desde F0. Las fases rellenan huecos, no reorganizan.

```
src/
├── Shared/
│   ├── Domain/          Aggregate/ Bus/ Clock/ Exception/ ValueObject/
│   └── Infrastructure/  Clock/ Http/ Persistence/Doctrine/
├── Experience/          Domain/ Application/ Infrastructure/
├── Session/             Domain/ Application/ Infrastructure/
└── Booking/             Domain/ Application/ Infrastructure/
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

### F0 — Esqueleto y contenedores
**Depende de:** — · **Duración:** 10 min

**Entrega** — Proyecto Symfony arrancable con base de datos, sin lógica de negocio.

**Crea**
- `compose.yaml` con servicios `php` (8.3-fpm), `nginx` y `db` (postgres:16), red interna y volumen de datos.
- `Dockerfile` con las extensiones `pdo_pgsql`, `intl` y Composer.
- Proyecto Symfony 7 mínimo (`symfony/framework-bundle`, `symfony/runtime`, `symfony/yaml`, `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, `symfony/messenger`, `symfony/uid`).
- El árbol de directorios completo del mapa anterior, con un `.gitkeep` por carpeta vacía.
- `.env` y `.env.test` con `DATABASE_URL` apuntando a `db`.

**No toca** — nada, es la primera fase.

**Acepta cuando**
- `docker compose up -d` deja los tres servicios en estado `running`.
- `curl -s -o /dev/null -w "%{http_code}" localhost:8080` devuelve un código HTTP, no un error de conexión.
- `docker compose exec php bin/console doctrine:query:sql "SELECT 1"` devuelve una fila.

---

### F1 — Calidad automatizada
**Depende de:** F0 · **Duración:** 10 min

**Entrega** — Un único comando que verifica estilo, tipos, capas y pruebas.

**Crea**
- `phpunit.xml.dist` con las suites `unit`, `application`, `functional` y `concurrency`; esta última excluida de la ejecución por defecto.
- `phpstan.neon` en `level: 9`, analizando `src` y `tests`.
- `deptrac.yaml` con las capas `Domain`, `Application`, `Infrastructure` por contexto, y las reglas 1 y 2 de las reglas permanentes expresadas como restricciones.
- `.php-cs-fixer.dist.php` con el conjunto `@Symfony` y `declare(strict_types=1)` obligatorio.
- `Makefile` con `up`, `down`, `test`, `stan`, `deptrac`, `cs`, `check` (los cuatro anteriores en cadena) y `concurrency`.

**No toca** — `compose.yaml`, `Dockerfile`, `src/`.

**Acepta cuando**
- `make check` termina con código 0 y sin ninguna prueba ejecutada.
- Un archivo de prueba que importe `Doctrine\ORM\EntityManager` desde `src/Experience/Domain` hace fallar `make deptrac`. Este archivo se borra tras comprobarlo.

---

### F2 — Núcleo compartido: identidad, dinero y reloj
**Depende de:** F1 · **Duración:** 15 min

**Entrega** — Los tipos base que usarán los tres contextos.

**Crea**
- `src/Shared/Domain/ValueObject/Uuid.php` — envuelve `Symfony\Component\Uid` solo en el método de generación; el resto opera sobre `string`.
- `src/Shared/Domain/ValueObject/Money.php` — `int $amount` en unidades mínimas, `string $currency` de 3 letras, `multiply(int $factor)`, `equals()`. Rechaza importes negativos y divisas mal formadas.
- `src/Shared/Domain/Clock/Clock.php` — interfaz con `now(): DateTimeImmutable`.
- `src/Shared/Infrastructure/Clock/SystemClock.php`.
- `src/Shared/Domain/Aggregate/AggregateRoot.php` — acumula eventos en memoria y los libera con `pullDomainEvents()`.
- `src/Shared/Domain/Bus/Event/DomainEvent.php` — interfaz con `aggregateId()` y `occurredOn()`.
- `tests/Shared/FrozenClock.php` — doble que devuelve siempre el instante que se le inyecta.
- `tests/Shared/Unit/MoneyTest.php`, `tests/Shared/Unit/UuidTest.php`.

**No toca** — configuración de F1, `compose.yaml`.

**Acepta cuando**
- `make check` en verde con al menos 8 pruebas unitarias.
- `Money::multiply(3)` sobre 1250 EUR devuelve 3750 EUR.
- Construir `Money` con importe negativo lanza `InvalidValue`.
- `grep -rn "new DateTimeImmutable()" src/ | grep -v SystemClock` no devuelve resultados.

---

### F3 — Núcleo compartido: errores y buses
**Depende de:** F2 · **Duración:** 10 min

**Entrega** — Jerarquía de excepciones de dominio traducida a HTTP, y los dos buses de mensajes.

**Crea**
- `src/Shared/Domain/Exception/DomainError.php` (abstracta, con `errorType(): string`) y las hijas `InvalidValue`, `NotFound`, `Conflict`.
- `src/Shared/Infrastructure/Http/ProblemJsonExceptionListener.php` — mapea las tres según la tabla de errores; cualquier otra excepción sale como `500` sin filtrar el mensaje interno.
- `config/packages/messenger.yaml` — bus `command.bus` con el middleware `doctrine_transaction`, y bus `event.bus` con `dispatch_after_current_bus`.
- `tests/Shared/Functional/ProblemJsonTest.php` — una ruta de prueba que lanza cada excepción y asevera código, `Content-Type` y forma del cuerpo.

**No toca** — `src/Shared/Domain/ValueObject`, `src/Shared/Domain/Clock`.

**Acepta cuando**
- `make check` en verde.
- Una `Conflict` produce `409`, `Content-Type: application/problem+json` y un cuerpo con las cuatro claves.
- Una `RuntimeException` produce `500` y su mensaje no aparece en la respuesta.

---

### F4 — Dominio Experience
**Depende de:** F2 · **Duración:** 10 min

**Entrega** — El agregado `Experience` y su puerto de persistencia.

**Crea**
- `src/Experience/Domain/Experience.php` — identidad, `ProviderId`, `Title`, `Description`, `DateTimeZone`. Método de fábrica `create()`.
- `src/Experience/Domain/ExperienceId.php`, `ProviderId.php`, `Title.php`, `Description.php`.
- `src/Experience/Domain/ExperienceRepository.php` — `save()`, `find(ExperienceId): ?Experience`.
- `src/Experience/Domain/ExperienceNotFound.php` extendiendo `NotFound`.
- `tests/Experience/Unit/ExperienceTest.php`.

`Title` rechaza cadenas vacías y de más de 150 caracteres. La zona horaria rechaza identificadores que no existan en la base de datos de husos.

**No toca** — `src/Shared`, `src/Session`, `src/Booking`.

**Acepta cuando**
- `make check` en verde.
- `Title` vacío y zona horaria `Mars/Olympus` lanzan `InvalidValue`.

---

### F5 — Aplicación Experience
**Depende de:** F3, F4 · **Duración:** 10 min

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
**Depende de:** F5 · **Duración:** 15 min

**Entrega** — Persistencia real y el primer endpoint vivo.

**Crea**
- `config/doctrine/Experience.orm.xml`.
- `migrations/VersionXXXXXX_experiences.php` — tabla `experiences` con clave primaria UUID.
- `src/Experience/Infrastructure/Persistence/DoctrineExperienceRepository.php`.
- `src/Experience/Infrastructure/Http/CreateExperienceController.php` y su entrada en `config/routes.yaml`.
- `tests/Experience/Functional/CreateExperienceTest.php`.

**No toca** — `src/Experience/Domain`, `src/Experience/Application`, el listener de F3.

**Acepta cuando**
- `make check` en verde.
- `POST /api/experiences` con cuerpo válido devuelve `201` y una cabecera `Location` que contiene el identificador generado.
- Falta `title` en el cuerpo → `422` con `type: invalid-value`.
- `grep -rn "ORM\\\\" src/Experience/Domain` no devuelve resultados.

---

### F7 — Dominio Session
**Depende de:** F4 · **Duración:** 15 min

**Entrega** — El agregado que custodia el aforo y las tres reglas temporales.

**Crea**
- `src/Session/Domain/Session.php` — identidad, `ExperienceId`, `startsAt`, `Capacity`, `seatsTaken`, `Money $price`. Métodos `schedule(Clock)`, `reserve(int $seats, Clock)`, `release(int $seats)`, `seatsAvailable()`, `hasStarted(Clock)`, `startsWithin(DateInterval, Clock)`.
- `src/Session/Domain/SessionId.php`, `Capacity.php`.
- `src/Session/Domain/SessionRepository.php` — `save()`, `find()`, `getForUpdate(SessionId): Session`, `hasSessionOnDay(ExperienceId, DateTimeImmutable $day)`.
- Excepciones `SessionNotFound`, `SessionDayTaken`, `SessionInThePast`, `SessionAlreadyStarted`, `NotEnoughSeats`.
- `tests/Session/Unit/SessionTest.php` usando `FrozenClock`.

`reserve()` lanza `NotEnoughSeats` cuando las plazas pedidas superan las disponibles, y `SessionAlreadyStarted` cuando el instante de inicio quedó atrás. `release()` nunca deja `seatsTaken` por debajo de cero.

**No toca** — `src/Experience`, `src/Shared`, `src/Booking`.

**Acepta cuando**
- `make check` en verde con al menos 10 pruebas nuevas.
- Reservar 3 sobre una sesión de aforo 10 con 8 ocupadas lanza `NotEnoughSeats` y deja el contador en 8.
- Programar una sesión con `startsAt` anterior al reloj congelado lanza `SessionInThePast`.

---

### F8 — Aplicación Session
**Depende de:** F5, F7 · **Duración:** 10 min

**Entrega** — El caso de uso de creación con la regla de un día, una sesión.

**Crea**
- `src/Session/Application/Create/CreateSessionCommand.php` y `CreateSessionCommandHandler.php`.
- `tests/Session/InMemorySessionRepository.php` — `getForUpdate()` se comporta como `find()`.
- `tests/Session/Application/CreateSessionTest.php`.

El manejador carga la experiencia para leer su zona horaria, convierte `startsAt` a ese huso y pregunta al repositorio si ya hay sesión ese día.

**No toca** — `src/Session/Domain`, `src/Experience`.

**Acepta cuando**
- `make check` en verde.
- Dos sesiones el mismo día civil de la experiencia → la segunda lanza `SessionDayTaken`.
- Dos sesiones separadas por 3 horas que caen en días civiles distintos en la zona de la experiencia → ambas se crean.
- Experiencia inexistente → `ExperienceNotFound`.

---

### F9 — Infraestructura Session
**Depende de:** F6, F8 · **Duración:** 15 min

**Entrega** — Persistencia con las dos garantías a nivel de esquema y los endpoints de sesión.

**Crea**
- `config/doctrine/Session.orm.xml`.
- `migrations/VersionXXXXXX_sessions.php` — tabla `sessions` con columna generada `session_day` (`DATE`), índice único sobre `(experience_id, session_day)`, y `CHECK (seats_taken >= 0 AND seats_taken <= capacity)`.
- `src/Session/Infrastructure/Persistence/DoctrineSessionRepository.php` — `getForUpdate()` usa `LockMode::PESSIMISTIC_WRITE`.
- `src/Session/Infrastructure/Http/CreateSessionController.php` y `GetSessionController.php`, más sus rutas.
- `tests/Session/Functional/SessionEndpointsTest.php`.

**No toca** — `src/Session/Domain`, `src/Session/Application`, `migrations/VersionXXXXXX_experiences.php`.

**Acepta cuando**
- `make check` en verde.
- `POST /api/experiences/{id}/sessions` válido → `201` con `Location`.
- Segunda sesión el mismo día → `409` con `type: session-day-taken`.
- Fecha pasada → `422`.
- `GET /api/sessions/{id}` devuelve las siete claves del contrato con `seatsTaken` a 0.
- Un `INSERT` directo en SQL que duplique `(experience_id, session_day)` es rechazado por la base de datos.

---

### F10 — Dominio Booking
**Depende de:** F7 · **Duración:** 15 min

**Entrega** — El agregado de reserva con su máquina de estados y sus eventos.

**Crea**
- `src/Booking/Domain/Booking.php` — identidad, `SessionId`, `UserId`, `seats`, `Money $total`, `BookingStatus`, `ContactEmail`. Fábrica `confirm()` y método `cancel(DateTimeImmutable $sessionStartsAt, Clock)`.
- `src/Booking/Domain/BookingStatus.php` como `enum` con los casos `Confirmed` y `Cancelled`.
- `src/Booking/Domain/BookingId.php`, `UserId.php`, `ContactEmail.php`, `Seats.php`.
- `src/Booking/Domain/BookingRepository.php` — `save()`, `find()`.
- Excepciones `BookingNotFound`, `BookingAlreadyCancelled`, `CancellationWindowClosed`.
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

### F11 — Aplicación Booking
**Depende de:** F8, F10 · **Duración:** 15 min

**Entrega** — Reserva y cancelación coordinando los dos agregados.

**Crea**
- `src/Booking/Application/Book/BookSeatsCommand.php` y `BookSeatsCommandHandler.php` — obtiene la sesión con `getForUpdate()`, invoca `reserve()`, construye la reserva y guarda ambos.
- `src/Booking/Application/Cancel/CancelBookingCommand.php` y `CancelBookingCommandHandler.php` — obtiene la sesión con `getForUpdate()`, cancela la reserva y devuelve las plazas con `release()`.
- `tests/Booking/InMemoryBookingRepository.php`.
- `tests/Booking/Application/BookSeatsTest.php` y `CancelBookingTest.php`.

**No toca** — `src/Booking/Domain`, `src/Session/Domain`, `src/Session/Application`.

**Acepta cuando**
- `make check` en verde.
- Reservar 3 sobre 10 libres deja la sesión con `seatsTaken` a 3 y la reserva en `confirmed`.
- Reservar 3 sobre 2 libres lanza `NotEnoughSeats` y deja la sesión intacta.
- Cancelar devuelve las plazas exactas al contador de la sesión.
- Cancelar una reserva ya cancelada no altera el contador.

---

### F12 — Infraestructura Booking
**Depende de:** F9, F11 · **Duración:** 15 min

**Entrega** — Los dos endpoints de reserva sobre transacción real con bloqueo.

**Crea**
- `config/doctrine/Booking.orm.xml`.
- `migrations/VersionXXXXXX_bookings.php` — tabla `bookings` con índice sobre `session_id` y `status` como cadena.
- `src/Booking/Infrastructure/Persistence/DoctrineBookingRepository.php`.
- `src/Booking/Infrastructure/Http/BookSeatsController.php` y `CancelBookingController.php`, más sus rutas.
- `tests/Booking/Functional/BookingEndpointsTest.php`.

Los controladores despachan por `command.bus`, de modo que el middleware `doctrine_transaction` de F3 envuelve el manejador completo.

**No toca** — `src/Booking/Domain`, `src/Booking/Application`, migraciones anteriores.

**Acepta cuando**
- `make check` en verde.
- `POST /api/sessions/{id}/bookings` válido → `201` con `Location`; un `GET` posterior de la sesión muestra el contador actualizado.
- Plazas insuficientes → `409` con `type: not-enough-seats`.
- Sesión ya empezada → `409` con `type: session-already-started`.
- `POST /api/bookings/{id}/cancellation` válido → `204`; segunda llamada → `409`.
- Sesión inexistente al reservar → `404`.

---

### F13 — Prueba de concurrencia
**Depende de:** F12 · **Duración:** 10 min

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
**Depende de:** F12 · **Duración:** 15 min

**Entrega** — La notificación como efecto posterior a la transacción.

**Crea**
- `src/Shared/Domain/Mailer.php` — puerto con `send(string $to, string $subject, string $body)`.
- `src/Shared/Infrastructure/Mailer/LoggerMailer.php`.
- `src/Shared/Infrastructure/Bus/Event/DoctrineDomainEventPublisher.php` — recoge los eventos de los agregados guardados y los despacha por `event.bus`.
- `src/Booking/Infrastructure/Notification/SendBookingConfirmationEmail.php` y `SendBookingCancellationEmail.php`.
- `tests/Booking/SpyMailer.php`.
- `tests/Booking/Functional/BookingNotificationTest.php`.

**No toca** — `src/Booking/Domain`, `src/Booking/Application`, `src/Session`, los controladores de F12.

**Acepta cuando**
- `make check` en verde.
- Una reserva con éxito produce exactamente una llamada al `Mailer`, dirigida al `contactEmail` de la petición.
- Una reserva que falla por aforo produce cero llamadas al `Mailer`.
- Forzar un fallo en el guardado tras invocar `reserve()` deja cero llamadas al `Mailer` y cero filas nuevas en `bookings`.
- `grep -rn "Mailer" src/Booking/Application` no devuelve resultados.

---

## Ciclo de trabajo

Una fase por conversación con el agente. El mensaje de arranque incluye las reglas permanentes, el contrato de la API y el bloque de la fase, nada más. Al terminar, `make check`, revisión humana del diff contra la lista `No toca`, y commit con el identificador de la fase como prefijo del mensaje.

Si una fase falla la verificación dos veces seguidas, se parte en dos en lugar de reintentar una tercera.
