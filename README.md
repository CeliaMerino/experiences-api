# Motor de reservas de experiencias

API REST para un marketplace de experiencias. Proveedores externos publican experiencias con sesiones datadas y aforo limitado; los usuarios finales reservan y cancelan plazas. Esta v1 cubre el núcleo transaccional: alta de inventario, venta de plazas y cancelación

---

## Stack


|                 |                                                    |
| --------------- | -------------------------------------------------- |
| Lenguaje        | PHP 8.4                                            |
| Framework       | Symfony 7.4                                        |
| Base de datos   | PostgreSQL 17                                      |
| Persistencia    | Doctrine ORM                                       |
| Pruebas         | PHPUnit 13                                         |
| Estilo          | PHP-CS-Fixer (`@Symfony` + `declare_strict_types`) |
| Reglas de capas | Deptrac                                            |
| Entorno         | Docker Compose                                     |


---



## Puesta en marcha

Requisitos: Docker y Docker Compose

```bash
# 1. Levantar los contenedores (php-fpm, nginx, postgres)
make up

# 2. Aplicar las migraciones sobre la base de datos de desarrollo
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# 3. Comprobar que la API responde
curl -i http://localhost:8080/api/experiences/00000000-0000-0000-0000-000000000000
#    → 404 con Content-Type: application/problem+json y type: experience-not-found
```

La API queda escuchando en `http://localhost:8080`. La base de datos de pruebas (`app_test`) se crea automáticamente al primer arranque de PostgreSQL; sus migraciones se aplican en el arranque de la suite de tests, no hace falta ejecutarlas a mano.

Para parar y limpiar:

```bash
make down
```

---



## Ejemplo de uso

Un recorrido completo por todos los casos de uso. Los identificadores de proveedor y de usuario son inventados (la v1 no tiene autenticación), y los importes van en la unidad mínima de la divisa (`2500` = 25,00 EUR).

```bash
# 1) Registrar una experiencia
curl -s -X POST http://localhost:8080/api/experiences \
  -H 'Content-Type: application/json' \
  -d '{
        "providerId": "3f1b2c4d-0000-4000-8000-000000000001",
        "title": "Descenso del Sella en kayak",
        "description": "Ruta guiada de 3 horas por el río Sella.",
        "timezone": "Europe/Madrid"
      }' -i
# → 201 Created
#   En los headers de la respuesta tenemos Location: /api/experiences/{experienceId}

# 2) Programar una sesión de esa experiencia
curl -s -X POST http://localhost:8080/api/experiences/{experienceId}/sessions \
  -H 'Content-Type: application/json' \
  -d '{
        "startsAt": "2030-07-15T10:00:00+02:00",
        "capacity": 10,
        "priceAmount": 2500,
        "priceCurrency": "EUR"
      }' -i
# → 201 Created
#   En los headers de la respuesta tenemos Location: /api/sessions/{sessionId}

# 3) Reservar 3 plazas
curl -s -X POST http://localhost:8080/api/sessions/{sessionId}/bookings \
  -H 'Content-Type: application/json' \
  -d '{
        "userId": "9a8b7c6d-0000-4000-8000-000000000002",
        "seats": 3,
        "contactEmail": "cliente@example.com"
      }' -i
# → 201 Created  ·  status "confirmed"  ·  total 7500 EUR
#   En los headers de la respuesta tenemos Location: /api/bookings/{bookingId}

# 4) Cancelar la reserva (a más de 24 h del inicio)
curl -s -X POST http://localhost:8080/api/bookings/{bookingId}/cancellation -i
# → 204 No Content  ·  las 3 plazas vuelven al inventario
```

Los errores de negocio se devuelven siempre en `application/problem+json` con `type`, `title`, `status` y `detail`. Por ejemplo, pedir más plazas de las disponibles responde `409` con `type: not-enough-seats` sin mover el contador de ocupación.

Los mismos siete endpoints están en la [colección de Postman](docs/experiences-api.postman_collection.json): importarla en Postman o compatible y apuntar a `http://localhost:8080`.

---



## Arquitectura

Arquitectura hexagonal con DDD y tres *bounded contexts* independientes más un núcleo compartido:

- **Experience** — el catálogo mínimo: título, descripción, referencia al proveedor y zona horaria.
- **Session** — custodia el aforo, el precio y la fecha. Aquí viven las reglas temporales y el contador de ocupación.
- **Booking** — la reserva, con su máquina de estados (`confirmed` → `cancelled`) y su importe congelado.
- **Shared** — tipos base transversales: `Uuid`, `Money`, el puerto `Clock`, la raíz de agregado, la jerarquía de errores de dominio y el puerto `Mailer`.

Cada contexto se divide en tres capas: **Domain** (agregados, objetos valor, puertos, reglas), **Application** (casos de uso como *command handlers*) e **Infrastructure** (adaptadores: Doctrine, controladores HTTP, correo).

**Regla de dependencia**, verificada automáticamente por Deptrac en cada `make check`:

- El dominio no importa `Symfony\`, `Doctrine\`, `Psr\` ni nada de infraestructura.
- La aplicación no importa `Doctrine\` ni `Symfony\Component\HttpFoundation`.
- Entre módulos: `Booking → Session → Experience → Shared`. Nunca al revés, y sin ciclos.

Los puertos (`Clock`, los tres repositorios, `Mailer`) son interfaces en `Domain`; sus implementaciones viven en `Infrastructure`. Esto mantiene el núcleo aislado y comprobable sin base de datos.

El detalle completo —flujos, precondiciones y extensiones de cada caso de uso, contrato de la API congelado y la tabla de errores— está en `[docs/](#documentación)`.

---



## API

Siete endpoints sobre tres recursos. 


| Método | Ruta                                       | Propósito                              |
| ------ | ------------------------------------------ | -------------------------------------- |
| `POST` | `/api/experiences`                         | Registrar una experiencia              |
| `GET`  | `/api/experiences/{experienceId}`          | Consultar una experiencia              |
| `POST` | `/api/experiences/{experienceId}/sessions` | Programar una sesión                   |
| `GET`  | `/api/sessions/{sessionId}`                | Consultar disponibilidad de una sesión |
| `POST` | `/api/sessions/{sessionId}/bookings`       | Reservar plazas                        |
| `GET`  | `/api/bookings/{bookingId}`                | Consultar una reserva                  |
| `POST` | `/api/bookings/{bookingId}/cancellation`   | Cancelar una reserva                   |


Cada `201` devuelve una cabecera `Location` que apunta a un `GET` existente. El **contrato completo** —cuerpos de petición, representaciones de cada recurso y la tabla de traducción de errores a HTTP— es la fuente de verdad congelada en `[docs/plan-fases.md](docs/plan-fases.md)`, sección «Contrato de la API».

---



## Robustez frente a concurrencia

1. **Transacción única con bloqueo pesimista.** La venta de una plaza se resuelve en una transacción que obtiene la fila de la sesión con `SELECT … FOR UPDATE`. Dos peticiones sobre la misma sesión se serializan: la segunda espera a que la primera confirme y lee el contador ya actualizado.
2. **Restricción en base de datos.** Una restricción a nivel de tabla rechaza toda escritura que deje el contador por encima del aforo. Aunque un defecto de software se saltara la comprobación de dominio, la base de datos no permite la sobreventa.
3. **Índice único para «una sesión por día».** La unicidad de sesión por experiencia y día civil se sostiene con un índice único sobre `(experience_id, día local)`; la violación se traduce a `409 session-day-taken`, nunca a `500`. El día se evalúa en la zona horaria de la experiencia, no en UTC.

Esto se comprueba con una **prueba de concurrencia real** (no con dobles): 50 peticiones simultáneas de 1 plaza sobre una sesión de aforo 10 dan exactamente **10 confirmaciones (**`201`**) y 40 rechazos (**`409`**)**, con el contador final en 10 y sin ninguna respuesta `500`.

```bash
make concurrency   # requiere los contenedores levantados (make up)
```

---



## Calidad y pruebas

Un único comando verifica estilo, tipos, reglas de capas y pruebas:

```bash
make check
```

Encadena, en orden:


| Objetivo       | Qué comprueba                                      |
| -------------- | -------------------------------------------------- |
| `make cs`      | Estilo de código (PHP-CS-Fixer, en modo *dry-run*) |
| `make stan`    | Análisis estático (PHPStan nivel 9)                |
| `make deptrac` | Reglas de dependencia entre capas y módulos        |
| `make test`    | Pruebas unitarias, de aplicación y funcionales     |


La prueba de concurrencia (`make concurrency`) se ejecuta aparte porque necesita el stack completo levantado y ataca la base de datos real.

Hay cuatro suites de pruebas:

- **unit** — el dominio en aislamiento, sin infraestructura. Una prueba por regla de negocio.
- **application** — los casos de uso contra repositorios en memoria y un reloj congelado.
- **functional** — los siete endpoints de extremo a extremo (`WebTestCase` contra `app_test`).
- **concurrency** — el ataque en paralelo descrito arriba.

El tiempo se inyecta como dependencia (`Clock`), así que la ventana de cancelación de 24 horas y la regla de «mismo día» se prueban de forma determinista con un reloj congelado.

---



## Estructura del proyecto

```
src/
├── Shared/
│   ├── Domain/          Aggregate/ Bus/Event/ Clock/ Exception/ ValueObject/ Mailer.php
│   └── Infrastructure/  Bus/Event/ Clock/ Http/ Mailer/ Persistence/Doctrine/
├── Experience/          Domain/ Application/ Infrastructure/
├── Session/             Domain/ Domain/Service/ Application/ Infrastructure/
└── Booking/             Domain/ Domain/Service/ Application/ Infrastructure/
tests/
├── Shared/  Experience/  Session/  Booking/   (dobles en la raíz · Unit/ Application/ Functional/)
└── Concurrency/
config/
├── doctrine/            Mapeo XML de los tres contextos
└── ...
migrations/              Esquema versionado (Doctrine Migrations)
docs/                    Documentación del proyecto
```

Los agregados tienen constructor privado y métodos de fábrica con nombre de intención (`create`, `schedule`, `confirm`), cero *setters*, y se referencian entre sí por identificador. La lógica que abarca dos agregados vive en un servicio de dominio, no en el *handler*.

---



## Alcance de la v1

**Incluido:** registrar experiencias, programar sesiones, reservar plazas, cancelar reservas, notificación por correo al reservar y al cancelar (adaptador simulado), y garantía de no sobreventa bajo concurrencia.

**Fuera de alcance (deuda declarada):** autenticación y autorización, pagos y reembolsos, gestión de proveedores y usuarios como entidades, catálogo y búsqueda, listas de espera, modificación de reservas, descuentos y tarifas variables, notificaciones más allá del correo, endpoints de listado y paginación, claves de idempotencia y patrón *outbox*.

La v1 asume un entorno cerrado: al no haber autenticación, cualquiera con el identificador podría operar sobre la reserva de otro. Es un riesgo asumido y **bloqueante para producción**. Por eso el `contactEmail` se persiste pero nunca se devuelve en las representaciones. El detalle de supuestos y deuda priorizada está en el dossier de entrega y en la bitácora de decisiones.

---



## Documentación


| Documento                                                                              | Contenido                                                                           |
| -------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `[docs/plan-fases.md](docs/plan-fases.md)`                                             | Contrato de la API congelado, reglas permanentes y plan de implementación por fases |
| `[docs/use-cases.md](docs/use-cases.md)`                                               | Los nueve casos de uso con flujos, precondiciones, extensiones y trazabilidad       |
| `[docs/decisiones.md](docs/decisiones.md)`                                             | Bitácora de decisiones tomadas durante la implementación, fase a fase               |
| `[docs/experiences-api.postman_collection.json](docs/experiences-api.postman_collection.json)` | Colección de Postman con los siete endpoints para importar y probar la API          |


