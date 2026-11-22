# Despliegue y buenas prácticas

## Entornos
- **Producción:** https://mcp.herbieai.app/ (servidor MCP y backoffice).
- **Repositorio privado:** código del servidor y conectores.
- **Repositorio público (este):** documentación, Skills & Rules y evidencia.

## Flujo de cambios
1. Rama por cambio y pull request revisado.
2. Fusión a `main` tras la revisión.
3. Publicación en producción desde `main`.
4. Verificación posterior: conectores en estado "Disponible" y consulta de prueba desde Claude o ChatGPT.

## Prácticas
- Secretos fuera del repositorio; configurados en el hosting.
- Bitácora de consultas y control de accesos en el servidor.
- Cada respuesta trazable: fuente, enlace y fecha.
- Publicación del plugin en Dojo Marketplace como canal de venta.
