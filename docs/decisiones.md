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
