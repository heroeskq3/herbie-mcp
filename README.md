# Herbie MCP · Seguridad jurídica para inversionistas en Costa Rica

Proyecto del equipo **DemocrackIA** para el **Dojo AI Hackathon 2026** (track Build for Business).

Herbie MCP es un servidor MCP empaquetado como plugin para **Claude** y **ChatGPT**. Centraliza datos, indicadores y transparencia de fuentes oficiales de Costa Rica (GICA, Registro Nacional, municipalidades, CCSS, MTSS, La Gaceta, SINALEVI, Poder Judicial) y los entrega verificados por multiagentes, con fuente, enlace y fecha.

> Herbie MCP entrega datos públicos verificados. **No emite recomendaciones ni dictámenes**: la decisión la toma el inversionista o su asesor.

- Demo en vivo: https://mcp.herbieai.app/
- Disponible en Dojo Marketplace

## El problema
Quien invierte en Costa Rica (zonas francas, franquicias, empresas, profesionales) enfrenta normas, trámites y riesgos repartidos en muchas instituciones. El resultado es incertidumbre jurídica que frena o encarece decisiones multimillonarias y abre espacio a influencias y sobornos.

## La solución
1. El usuario pregunta desde la IA que ya usa (Claude o ChatGPT).
2. El orquestador de Herbie MCP consulta los conectores a fuentes oficiales.
3. Las **Skills & Rules** de seguridad jurídica guían el análisis del caso.
4. **Multiagentes** verifican cada dato contra otras fuentes (normativa costarricense e internacional, Sala IV, TSE, red de transparencia).
5. La respuesta llega con fuente, enlace y fecha, y puede convertirse en un **informe final (Artifacts)**.

No es otra IA: aprovecha el potencial agéntico de Claude y ChatGPT y les provee datos fidedignos.

## Qué se construyó en el hackathon (20 al 22 de noviembre de 2026)
- Conectores de inversión: Registro Nacional, municipalidades, CCSS, MTSS, DGME, SICOP y GICA como centro de indicadores.
- Skills & Rules de seguridad jurídica para inversionistas (ver `skills/`).
- Multiagente verificador y respuestas trazables.
- Capacidad de Artifacts para informes y acompañamiento.
- Empaque como plugin y publicación en Dojo Marketplace.

Punto de partida y bitácora: ver `docs/`.

## Nuevos indicadores y orquestación MCP

- [Estado de la Nación y BCCR](docs/indicadores-inversion.md): herramientas, fuentes, acceso y límites. PEN permite buscar y leer publicaciones; BCCR requiere token propio para consultar sus series SDDE.
- [Orquestación MCP actualizada](docs/orquestacion-mcp.md): selección de fuentes, reglas de comparación, cruce jurídico y generación de Artifacts a petición del usuario.

Estos indicadores son insumos para el análisis del asistente cliente; el MCP no genera pronósticos ni recomendaciones. La consulta BCCR autenticada continúa pendiente de credencial válida.

## Cómo usarlo
1. Entrar a https://mcp.herbieai.app/ y crear una cuenta.
2. En **Conectores → MCP**, conectar Claude o ChatGPT.
3. Preguntar, por ejemplo: «¿Qué debo revisar para abrir una franquicia en un cantón de Heredia?»

## Equipo
- **Herbert Poveda** · capitán, desarrollo e integración técnica.
- **Kristel Gutierrez** · abogada; compras públicas (SICOP), derecho laboral y migratorio; validación jurídica.
- **Juan Carlos Campos** · abogado; zonas francas, franquicias y compras; especialista y promotor de la iniciativa.

## Alcance de este repositorio
Repositorio público de la entrega del hackathon: documentación, Skills & Rules y evidencia del evento. El código del servidor de Herbie MCP se mantiene en un repositorio privado.
