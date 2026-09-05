# Bitácora de decisiones

Registro acumulativo de lo que se decidió durante la implementación y no estaba escrito en `plan-fases.md`, `use-cases.md` ni en las reglas de `.cursor/rules/`.

Solo crece. Una entrada por fase, añadida al cerrarla. Lo que ya está en un documento no se duplica aquí: se referencia.

**Qué entra** — decisiones que tenían más de una salida razonable · supuestos asumidos sin confirmar · divergencias entre lo que decían los documentos y lo que se hizo · deuda que la fase deja abierta.

**Qué no entra** — repetir el contrato de la API, las reglas permanentes o el mapa de directorios · nombres de métodos privados · detalles que se deducen del código.

---

## Formato

```
## F{N} — {título de la fase}
**Fecha:** AAAA-MM-DD · **Commit:** {sha corto}

### Decisiones
| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|

### Supuestos
Cosas que se dieron por buenas sin confirmar y que habría que validar.

### Divergencias
Dónde lo implementado no coincide con lo que decía el documento, y por qué.

### Deuda abierta
Lo que esta fase deja pendiente y en qué fase o versión se recoge.
```

Si una fase no generó nada de esto, la entrada lo dice en una línea y no se inventa contenido.

---

## F-1 — Auditoría del repositorio

**Fecha:** 2026-09-05 · **Commit:** —

Reconciliación previa a F0. Lo elegido quedó escrito en `docs/plan-fases.md`, `docs/use-cases.md`, `.cursor/rules/00-arquitectura.mdc` y `.cursor/rules/10-contrato-api.mdc`. Aquí solo el porqué.

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | Stack PHP 8.4 (imagen, `composer.json` `>=8.4`, plan y reglas). PHPUnit 13 se conserva. | Bajar a PHPUnit 12 y quedarse en 8.3, o subir el stack a 8.5 como el host. | El lock ya exige `phpunit/phpunit` 13.3.2 (`>=8.4.1`). 8.4 es el mínimo que hace cierto el stack «PHPUnit 13» sin atarse al 8.5 de esta máquina. |
| 2 | F0 crea `docker/nginx/default.conf` y el curl de aceptación exige cuerpo de Symfony, no la welcome de nginx. | Embeber la config en `compose.yaml`. | Fichero revisable; sin él F0 pasaría con nginx por defecto y PHP desconectado. |
| 3 | Bind-mount del proyecto en `php` y `nginx`. `make` (salvo `up`/`down`) es `docker compose exec php`. | Publicar `5432:5432` y correr `make` en el host. | F0 fija `DATABASE_URL` al host `database`. F1 no puede tocar Compose. Un solo PHP: el de la imagen. |
| 4 | `ext-curl` en el `Dockerfile` de F0. | Añadirla en F13. | F13 no lista el `Dockerfile` en `Crea`. `curl_multi` es F9 y F13. |
| 5 | F1 pone `declare(strict_types=1)` en `Kernel.php`, `public/index.php`, `bin/console` y `tests/bootstrap.php`. `No toca src/` admite esa excepción en `Kernel.php`. | Excluir los PHP de Flex del fixer. | Si no, `make cs` falla o viola el no-toca. |
| 6 | `defaultTestSuite` = `unit,application,functional`. Sin `--exclude-testsuite`. `--do-not-fail-on-empty-test-suite` en `test` y `check`. | Un test dummy para no dejar la suite vacía. | PHPUnit 13 sale con código 1 si filtras por suite y no corre nada. |
| 7 | `Uuid` genera v4 con `random_bytes`. Domain no importa `Symfony\`. | Excepción a la regla 1 para `Symfony\Component\Uid`. | La regla 1 se mantiene inviolable; `symfony/uid` sigue instalado por si el borde HTTP lo usa. |
| 8 | F3 crea controlador y `config/routes/test.yaml` solo en `APP_ENV=test`. | Probar el listener sin HTTP. | El `Acepta cuando` de F3 es de borde HTTP (400/415/cuerpo). |
| 9 | F0 crea `app_test` vía init SQL. F6 aplica migraciones en `tests/bootstrap.php`. | Quitar el `dbname_suffix` y reutilizar `app`. | Sin paquetes nuevos y sin mezclar datos de test con los de F13. |
| 10 | F2 registra `FrozenClock` como `Clock` en `config/packages/test/clock.yaml`. | Reloj congelado solo en unitarias. | La regla 20 exige tiempo inyectado también en funcionales (ventana de 24 h). |
| 11 | El mapa admite `Mailer.php` en Domain y dobles en la raíz de `tests/<Módulo>/`. Las listas `Crea` no se mueven. | Reubicar cada doble bajo `Unit/` y `Mailer` a un subdirectorio. | Menos movimiento; el mapa deja de contradecir F2/F5/F8/F11/F14. |
| 12 | F4 añade `ExperienceRepository::get()` que lanza `ExperienceNotFound`. F8 llama `get()`. | Permitir `?? throw` en el manejador. | El «sin `if`» de F8 se puede cumplir sin tocar Experience en F8. |
| 13 | El NFR de UC-05 es el de F13 (50 peticiones, 10×201 y 40×409). Se elimina p95/200. | Nueva fase de carga. | v1 no tiene métricas; F13 ya era la evidencia de aforo. |

### Supuestos

- El volumen `db_data` de Compose está vacío o se recrea: el init de `app_test` solo corre en el primer arranque de Postgres.
- El host puede tener PHP 8.5; no es el runtime de `make check`.

### Divergencias

Ninguna respecto a lo implementado: esta entrada solo alinea documentos. El repo de código sigue siendo el esqueleto Flex.

### Deuda abierta

Ninguna de esta auditoría. F0 queda desbloqueada.

---

## F0 — Contenedores de aplicación
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | `DATABASE_URL` también como variable de entorno del servicio `php` en `compose.yaml`. | Confiar solo en `.env` / `.env.test`. | En este checkout `.env.local` apunta a `127.0.0.1:54321` y Symfony lo carga encima de `.env`. La variable real gana; no se toca el fichero gitignored. |
| 2 | Tres mappings XML en `config/doctrine/{Experience,Session,Booking}` con prefijos `App\<Contexto>\Domain`. | Un directorio plano `config/doctrine/*.orm.xml`, o un único mapping `prefix: App`. | DoctrineBundle 3 registra un `SimplifiedXmlDriver` y hace `array_flip(prefix → dir)`: tres prefijos sobre el mismo `dir` se colapsan. F6/F9/F12 no tocan `doctrine.yaml`. |
| 3 | F0 se cierra con sus cuatro `Acepta cuando`, sin `make check`. | Adelantar el `Makefile` o invocar phpstan/cs a mano. | El `Makefile` nace en F1. La regla permanente 11 cede ante el bloque específico de F0. |

### Corrección del documento

En `docs/plan-fases.md`, el tercer criterio de F0 decía `doctrine:query:sql`. Ese comando era un alias de DoctrineBundle 2.x, deprecado en 2.2 y retirado en 3.0. Con `doctrine/doctrine-bundle` ^3.3 no existe. Se sustituyó por `dbal:run-sql`. No es una divergencia de implementación: el criterio estaba mal escrito.

### Supuestos

- El volumen `db_data` estaba vacío al primer `up`; el init de `app_test` solo corre entonces.
- `vendor/` del host, bind-montado, es usable por PHP 8.4-fpm. No se ejecutó `composer install` en la imagen.

### Divergencias

Ninguna respecto al criterio ya corregido. Los XML de F6/F9/F12 quedan en `config/doctrine/<Contexto>/<Agregado>.orm.xml`, no en `config/doctrine/<Agregado>.orm.xml` como listan esas fases.

### Deuda abierta

- `composer.lock` sigue con `"platform"."php": ">=8.2"`; no se ejecutó `composer update --lock`.
- F6 (y F9, F12) deberán crear el XML en el subdirectorio de su contexto.

---

## Auditoría F1–F14 — Defectos del documento
**Fecha:** 2026-09-05 · **Commit:** —

No es una fase. Recoge lo aprobado en la pasada de auditoría sobre `docs/plan-fases.md`. Son defectos del documento, no divergencias de implementación.

### Correcciones aprobadas

| # | Fase | Lista | Antes | Después | Motivo |
|---|---|---|---|---|---|
| 1 | F6 | Crea | `config/doctrine/Experience.orm.xml` | `config/doctrine/Experience/Experience.orm.xml` | El `dir` de Doctrine apunta a `config/doctrine/Experience/`. El Crea nombraba un hermano de ese directorio; el driver no lo lee. F6 no lista `doctrine.yaml`. |
| 2 | F9 | Crea | `config/doctrine/Session.orm.xml` | `config/doctrine/Session/Session.orm.xml` | Igual que #1 con el mapping Session. |
| 3 | F12 | Crea | `config/doctrine/Booking.orm.xml` | `config/doctrine/Booking/Booking.orm.xml` | Igual que #1 con el mapping Booking. |

Opción A (corregir el documento). La B (mover el `dir` en `doctrine.yaml` a un plano) se descartó: tres prefijos sobre el mismo `dir` se colapsan en DoctrineBundle 3.

Esto cierra la deuda abierta de F0 sobre la ruta de esos XML.

### Filas rechazadas o aplazadas

Ninguna.

### Sospechosos descartados (no volver a reportar)

| # | Sospecha | Por qué no es defecto |
|---|---|---|
| 1 | F1 crea `phpstan.neon`, ignorado por `.gitignore` | El Crea ya dice que no se crea. `phpstan.dist.neon` no está ignorado. |
| 2 | F1 exige `deptrac.yaml` sin el paquete | Instala declara `deptrac/deptrac`. |
| 3 | F1 crea `phpunit.xml.dist` | El Crea nombra el `phpunit.dist.xml` ya existente y prohíbe el rename. |
| 4 | F14 `LoggerMailer` sin monolog ni servicio `logger` | F14 Instala `symfony/monolog-bundle`. El servicio `logger` ya existe sin monolog (`Symfony\Component\HttpKernel\Log\Logger`). |

---

## F1 — Calidad automatizada
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | Capas técnicas de deptrac nominadas por módulo (`ExperienceDomain`, …). | Un `Domain` / `Application` / `Infrastructure` globales solapados con capas de módulo. | El solape marca como violación el `Booking → Session` legal (regla 9). |
| 2 | Excluir `config/preload.php` del finder de CS Fixer (consulta 1, opción A). | Añadir `declare(strict_types=1)` al preload, o ampliar `Crea` en el plan. | El fichero no está en `Crea`; mismo trato que `bundles.php` / `reference.php`. |
| 3 | Quitar el `method_exists` de `bootEnv` en `tests/bootstrap.php` (consulta 2, opción A). | `ignoreErrors` en PHPStan, o sacar el bootstrap de los paths. | En Symfony 7.4 `bootEnv` siempre existe; el guardia es código muerto y rompe level 9. |
| 4 | `setRiskyAllowed(true)`, excluir `vendor` y `append` de `bin/console` en el fixer. | Dejar el finder Flex tal cual. | Sin risky, `declare_strict_types` no aplica; sin excluir `vendor`, `cs` falla; `bin/console` no termina en `.php`. |
| 5 | Cache de deptrac en `var/.deptrac.cache`. | `.deptrac.cache` en la raíz. | La raíz habría exigido tocar `.gitignore` (fuera de alcance). |

### Supuestos

- `defaultTestSuite="unit,application,functional"` con coma es válido en PHPUnit 13.3 (el merger hace `explode(',', …)`).

### Divergencias

Ninguna respecto al bloque de F1.

### Deuda abierta

Ninguna de esta fase.

---

## F2 — Núcleo compartido: errores, identidad, dinero y reloj
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | `InvalidValue` concreta; `NotFound` y `Conflict` abstractas. | Las tres concretas con `errorType` genéricos. | La tabla de errores solo tipifica `invalid-value` como padre usable; los 404/409 son siempre hijas de contexto. |
| 2 | `DomainError extends \DomainException`. | Extender `\Exception` o `\RuntimeException`. | Encaja el significado; no cambia el contrato HTTP. |
| 3 | Alias prod `Clock` → `SystemClock` vía `#[AsAlias]` en `SystemClock`. | Entrada en `config/services.yaml`. | `services.yaml` no está en `Crea`; el atributo vive en un fichero que sí lo está. |
| 4 | `FrozenClock::at(string)` + parámetro `frozen_clock.now` en el YAML de test. | Solo constructor + ExpressionLanguage en el YAML. | Evita depender del language; el instante sigue siendo inyectable por parámetro. |
| 5 | `Money::of` / `Uuid::fromString` + `equals`/`value`; divisa `^[A-Z]{3}$`. | Constructor público; aceptar minúsculas. | Misma forma que el resto de VOs previstos; ISO-4217 en mayúsculas evita ambigüedad. |

### Supuestos

- El binding `Clock` → `FrozenClock` en test no se ejercita en esta fase (no hay funcional en `Crea`); se asume correcto hasta F3/F7.

### Divergencias

Ninguna respecto al bloque de F2.

### Deuda abierta

Ninguna de esta fase.

---

## F3 — Borde HTTP: errores y buses
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | `MalformedJson.php` y `UnsupportedMediaType.php` como ficheros propios (consulta 1, A). | Ambas clases en `JsonRequestDecoder.php`. | PSR-4; el párrafo del Crea las sitúa en ese directorio aunque no las liste como línea. |
| 2 | `type` del 500 = `about:blank` (consulta 2, A). | `internal-server-error`. | RFC 7807 cuando no hay tipo asignado; no inventa un valor que parezca de la tabla congelada. |
| 3 | Listener a prioridad `-1` (consulta 3, A). | `-129` (después del `ErrorListener`) o `10` (antes del log). | `ExceptionEvent::setResponse()` corta la propagación. A `-1` corre después de `logKernelException` (0) y antes de que el `ErrorListener` (`-128`) ponga HTML. |
| 4 | `default_bus: command.bus`; `event.bus` con `allow_no_handlers` y sin repetir `dispatch_after_current_bus`. | Listar el middleware por defecto a mano, o dejar el bus sin nombre por defecto. | Symfony 7.4 exige `default_bus` con más de un bus; el `dispatch_after_current_bus` ya va en el middleware por defecto. |
| 5 | JSON con claves no string (lista) se rechaza como `MalformedJson`. | `replace()` directo del `json_decode`. | `InputBag::replace()` exige `array<string, mixed>`; PHPStan level 9 lo marca. El contrato solo envía objetos. |
| 6 | `config/routes/test.yaml` envuelto en `when@test:`. | Subdirectorio `config/routes/test/` (no es el path del Crea). | El glob de Flex carga `config/routes/*.yaml` en todos los envs; el `when@` es el mismo patrón que `framework.yaml`. |

### Supuestos

- El binding `Clock` → `FrozenClock` en test se carga al arrancar el kernel de los funcionales; no se asevera el instante.
- `doctrine_transaction` en `command.bus` no se ejercita hasta que existan manejadores (F5/F12).

### Divergencias

- Dos ficheros de excepción HTTP-edge que el Crea nombra como clases pero no como líneas de fichero. Autorizado en consulta 1.
- La prioridad del listener no puede ser posterior al `ErrorListener`: el API de Symfony lo impide. Autorizado en consulta 3.

### Deuda abierta

Ninguna de esta fase. Los buses quedan sin prueba PHPUnit porque el `Acepta cuando` no los pide; F12 los usa.

---

## F4 — Dominio Experience
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | `Experience::create()` recibe un `ExperienceId` ya construido. | Generar el id dentro de la fábrica. | F5 asigna la generación al manejador y lo devuelve al llamante. |
| 2 | `Title::of` recorta y trata solo espacios como vacío. Longitud con `strlen`. | Rechazar únicamente `''`; contar con `iconv_strlen`/`grapheme_strlen`. | «Título vacío» es lenguaje de negocio. Los criterios de aceptación son ASCII; `mbstring` no está en la imagen. |
| 3 | `Description` acepta cualquier cadena, sin recortar. | Rechazar vacío o imponer un máximo. | UC-01 no declara regla sobre la descripción. |
| 4 | `ProviderId` envuelve `Uuid`, igual que `ExperienceId`. | Cadena opaca no vacía. | Reutiliza F2. Un valor mal formado sale como `InvalidValue`, no como un `type` nuevo. |
| 5 | Zona horaria vía `new \DateTimeZone($id)` capturando `\Exception`. | `timezone_identifiers_list()`. | `Mars/Olympus` falla igual; no exige un VO ni un fichero fuera de `Crea`. |
| 6 | `Experience` no extiende `AggregateRoot`. | Extenderlo por ser agregado. | Este contexto no registra eventos en el plan. |

### Supuestos

- F5 implementará `ExperienceRepository::get()` como `find() ?? throw new ExperienceNotFound($id)`. El puerto lo declara; F4 no tiene adaptador que ejecutar.

### Divergencias

Ninguna respecto al bloque de F4.

### Deuda abierta

Ninguna de esta fase. Persistencia, caso de uso de alta y endpoints quedan en F5 y F6.

---

## F5 — Aplicación Experience
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | `config/packages/test/experience.yaml` aliasa `ExperienceRepository` → `InMemoryExperienceRepository` (consulta 1, A). | Excluir el manejador del contenedor; binding en `services.yaml`; impl temporal en `src/`. | Sin impl del puerto, el contenedor de test no compila al registrar el manejador. Mismo patrón que `Clock` en F2. |
| 2 | Extensiones UC-01 cubiertas en tests de aplicación con `InvalidValue` (consulta 2, A). | Exigir funcionales HTTP ya en F5. | El `Crea` de F5 no incluye endpoints; el HTTP `422` queda en F6. Gana `plan-fases.md` frente a la regla 20. |
| 3 | Handler con `#[AsMessageHandler(bus: 'command.bus')]`; retorno `ExperienceId`; command con props `public readonly`. | Sin atributo; retorno `string`; getters. | Autoconfigure para F6; tipo fuerte; forma habitual Symfony 7. |

### Supuestos

- En F6, al existir `DoctrineExperienceRepository`, el alias de test seguirá apuntando al doble en memoria salvo que esa fase lo retire o lo reapunte. Los funcionales de F6 deben usar Doctrine, no el InMemory.

### Divergencias

- Un fichero de `config/packages/test/` no listado en el `Crea` original. Autorizado en consulta 1.

### Deuda abierta

- Retirar o reasignar `config/packages/test/experience.yaml` cuando F6 registre la impl Doctrine, para que los funcionales no persistan en memoria.

---

## F6 — Infraestructura Experience
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | Tipos DBAL en `Shared/.../Doctrine/DateTimeZoneType` y en `Experience/.../Persistence/*Type` (consulta 1, A). Registro con `Type::addType` en el constructor del repositorio. | Tocar `doctrine.yaml`; solo embeddables. | F0: F6 no toca `doctrine.yaml`. Los tipos de Experience no caben en `Crea`; se autorizaron. |
| 2 | Borrar `config/packages/test/experience.yaml` (consulta 2, A). Binding vía `#[AsAlias]` en `DoctrineExperienceRepository`. | Reapuntar el alias; dejar InMemory en test. | Cierra la deuda de F5; los funcionales usan Doctrine. Los tests de aplicación siguen instanciando InMemory a mano. |
| 3 | `ExperienceId` implementa `\Stringable` (consulta 4, A). | Embeddables anidados; cambiar el id a `string`. | Doctrine ORM 3 hace `implode` del identificador en el UnitOfWork; sin `__toString` el persist falla. Excepción mínima al No toca de Domain. |
| 4 | `#[AsController]` en ambos controladores; unwrap de `HandlerFailedException` en el de alta. | Tocar el listener de F3; dejar el 500. | Sin tag el contenedor no expone el controlador. Messenger envuelve `InvalidValue` y el listener de F3 no desempaqueta (No toca). |

### Supuestos

- `app_test` ya existe (init de F0). El bootstrap solo migra.
- El aviso deptrac de `DateTimeZoneType` en dos capas (SharedInfrastructure + Doctrine) se tolera; no es violación.

### Divergencias

- Ficheros de tipos Doctrine y el borrado de `experience.yaml` fuera del `Crea` literal; autorizados en consultas 1 y 2.
- Un método en Domain (`ExperienceId::__toString`) pese al No toca; autorizado en consulta 4.

### Deuda abierta

- SessionId/BookingId necesitarán el mismo `\Stringable` cuando F9/F12 persistan.
- El listener de F3 sigue sin desempaquetar `HandlerFailedException`; cada controlador que despache por el bus debe hacerlo (o se centraliza en una fase que toque el listener).

---

## F7 — Dominio Session
**Fecha:** 2026-09-05 · **Commit:** —

### Decisiones

| # | Decisión | Alternativa descartada | Motivo |
|---|---|---|---|
| 1 | `Seats::of` exige ≥ 1; `Seats::none()` representa el cero del contador. | `of` admite 0. | F10 pide `InvalidValue` al construir `Seats` con 0; `seatsAvailable()` tiene que poder ser 0. |
| 2 | `SessionId` implementa `\Stringable` ya. | Dejarlo para F9. | F9 no toca Domain. Cierra la deuda de F6 sobre SessionId; BookingId sigue abierta. |
| 3 | `Session::schedule` recibe un `SessionId` ya construido. | Generarlo dentro de la fábrica. | Misma forma que `Experience::create` (F4). F8 puede hacer `SessionId::generate()` sin `if`. |
| 4 | `startsAt <= now` es pasado (`SessionInThePast` / `hasStarted`). | Solo `<`. | UC-03 exige fecha *posterior* al reloj; el igual no es posterior. |
| 5 | `release` de más plazas que las tomadas lanza `InvalidValue`. | Clampear a 0. | El clamp ocultaría un invariante roto. No hay tipo nuevo en la tabla de errores. |
| 6 | `getForUpdate` lanza `SessionNotFound` (no devuelve null). | `?Session`. | El `Crea` declara retorno `Session`. F11 no puede meter un `if`. |

### Supuestos

- F8 convertirá `startsAt` a la zona de la experiencia antes de llamar a `Session::schedule`. El agregado compara instantes, no días civiles.
- El bloqueo pesimista de `getForUpdate` es cosa del adaptador Doctrine en F9; el puerto solo declara el contrato.

### Divergencias

Ninguna respecto al bloque de F7.

### Deuda abierta

Ninguna de esta fase. Planificador, persistencia y endpoints quedan en F8 y F9. BookingId sigue debiendo `\Stringable` (deuda de F6).
