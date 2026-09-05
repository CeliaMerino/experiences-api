---
description: Protocolo de trabajo por fases. Doble puerta, consulta obligatoria, informe de cierre y bitácora de decisiones.
globs:
alwaysApply: true
---

# Cómo se trabaja

Una fase por conversación. Cada fase tiene dos puertas y no se cruza la segunda sin autorización explícita.

**Puerta 1 — Plan.** Lees el bloque de la fase en `docs/plan-fases.md`, inspeccionas el estado real del repo y devuelves un plan. No escribes ni modificas ningún archivo en este turno. Terminas esperando aprobación.

**Puerta 2 — Implementación.** Solo después de que el humano escriba `APRUEBO`. Implementas el plan aprobado, ni más ni menos. Si al implementar aparece algo que el plan no preveía, paras y consultas en lugar de resolverlo.

**Cierre.** Informe de verificación y entrada en la bitácora. El commit lo hace el humano, nunca tú.

# Qué obliga a parar

Paras y preguntas —con el marcador `⛔ CONSULTA`— ante cualquiera de estas, sin excepción y aunque la respuesta te parezca obvia:

- Contradicción entre `docs/plan-fases.md`, `docs/use-cases.md`, las reglas de `.cursor/rules/` y el estado real del repo.
- Un archivo que haga falta y no esté en la lista `Crea` de la fase.
- Un archivo de la lista `Crea` que ya exista con contenido distinto al que la fase describe.
- Instalar, actualizar o retirar cualquier dependencia.
- Cambiar una versión del stack, una configuración de `config/`, `compose.yaml`, `.gitignore` o un archivo de la lista `No toca`.
- Tocar el contrato de la API, la tabla de errores, el mapa de directorios o la jerarquía de excepciones.
- Cualquier cosa de la lista `Fuera de alcance`.
- Un criterio de `Acepta cuando` que no puedas verificar tal como está escrito.
- Un fallo que se arregle de más de una manera razonable.

Al parar: describes el problema, das las opciones con sus consecuencias, señalas cuál recomiendas y por qué, y esperas. No implementas ninguna mientras esperas. Una consulta sin opciones no sirve; una consulta con una sola opción tampoco.

Lo que no obliga a parar: nombres de métodos privados, orden de las aserciones, redacción de los mensajes de test. Van en el plan de la Puerta 1 como decisiones locales, y aprobar el plan las aprueba.

# Prohibiciones de sesión

- No ejecutas `git commit`, `git push`, `git reset` ni `git checkout`. El control de versiones es del humano.
- No ejecutas `composer require`, `composer update` ni `npm install` sin autorización de la Puerta 1.
- No creas archivos fuera de la lista `Crea` de la fase. La única excepción es `docs/decisiones.md`.
- No adelantas trabajo de fases posteriores ni rehaces fases cerradas.
- No declaras una fase terminada sin haber ejecutado la verificación y pegado su salida real.

# Informe de cierre

Al terminar la implementación devuelves, en este orden:

1. **Archivos tocados** — ruta y una línea de qué hace cada uno. Marcas los que no estaban en `Crea`; si hay alguno, es que algo falló en la Puerta 1 y lo dices.
2. **Cómo probarlo** — comandos exactos, en orden, listos para pegar en una terminal, cada uno con la salida que debe producir. Cuando la fase levanta endpoints, `curl` completos con cuerpos reales y encadenados: el identificador que devuelve una llamada se usa en la siguiente, y dices qué valor sustituir. Cada criterio de `Acepta cuando` aparece aquí con su comando.
3. **Lo que todavía no funciona** — qué es normal que falle a esta altura del plan, para que un error esperado no parezca un defecto.
4. **Decisiones tomadas** — las locales que se aprobaron en la Puerta 1 y cualquier consulta resuelta durante la implementación.
5. **Para deshacer** — el comando que devuelve el repo al estado anterior.

# Bitácora

`docs/decisiones.md` es acumulativa y solo crece: nunca reescribes ni borras entradas anteriores. Una entrada por fase, añadida al cerrarla.

Registras únicamente lo que no esté ya escrito en `docs/plan-fases.md`, `docs/use-cases.md` o las reglas de `.cursor/rules/`. Si algo ya está en un documento, no se duplica: se referencia.

Cada entrada lleva: fase, fecha, decisiones tomadas con su alternativa descartada y el motivo, supuestos asumidos que habría que confirmar, divergencias respecto a lo que decían los documentos, y deuda que la fase deja abierta. Si una fase no generó nada de esto, la entrada dice exactamente eso en una línea.
