# Orquestación MCP de indicadores para inversionistas

Actualización del 22 de noviembre de 2026. Documenta el comportamiento previsto y las instrucciones implementadas; no contiene el código privado del servidor.

## Responsabilidades

El **LLM cliente** —o el asistente configurado en Hai— decide qué herramientas consultar y razona sobre sus resultados. El **servidor MCP** consulta fuentes y devuelve datos, texto nativo u originales. Estas herramientas no incorporan un LLM ni ejecutan una evaluación automática de rentabilidad o seguridad jurídica.

El grupo de herramientas `inversion` reúne Estado de la Nación y BCCR. Las instrucciones del servidor incluyen reglas para el cliente MCP externo y para el coordinador de Hai cuando activa ese grupo.

## Flujo

1. Identificar la actividad, territorio, horizonte y datos necesarios de la consulta; pedir solo información que falte.
2. Para empleo, talento, educación, ambiente y territorio: consultar `estado_nacion_fuentes`, buscar publicaciones y leer los originales relevantes.
3. Para macroeconomía: consultar `bccr_fuentes`, buscar códigos en el catálogo vigente con `bccr_buscar` y después usar `bccr_indicador` con fechas y paginación.
4. Si falta el token BCCR o la fuente lo rechaza, informar el bloqueo y señalar la configuración segura; nunca pedir la credencial en el chat ni simular cifras.
5. Conservar URL, período observado, unidad, frecuencia y territorio; citar fecha de publicación si existe y separarla de la fecha de consulta.
6. Comprobar compatibilidad antes de calcular o comparar: nominal/real, stock/flujo, unidades, periodicidad y cobertura. Distinguir datos originales, cálculos y escenarios.
7. Cuando se pide un cruce jurídico, activar también las fuentes jurídicas; una estadística o noticia no determina requisitos ni acredita vigencia normativa.
8. Cuando se solicita un informe visual, consultar `artifact_especificacion` y usar las herramientas de Artifacts. Crear un informe no autoriza publicarlo ni enviar invitaciones.

## Lo que no se promete

No hay recomendaciones ni dictámenes automáticos del MCP. No hay una puntuación universal de riesgo ni cobertura completa de todos los indicadores nacionales. La disponibilidad de las fuentes y sus credenciales limita la consulta. No se interpreta ausencia como cero ni se presenta una consulta parcial como exhaustiva.

La posibilidad de colaboración o aportes depende de permisos implementados: un enlace público de lectura no equivale a autorización para editar.

## Evidencia

Se verificó la presencia de las instrucciones en las rutas del coordinador y del MCP externo, y la pertenencia de las seis herramientas al grupo de inversión. El conjunto de contrato pasó **225 comprobaciones**. Esto valida el contrato y selección de instrucciones; no demuestra una consulta BCCR autenticada ni una ejecución real de razonamiento del LLM.

Fuentes y estado de integración: [conectores de indicadores](indicadores-inversion.md).
