# Formulario de reflexión — FreshStock

## Sección 1: Mi propuesta

### 1. ¿Qué contexto base me asignaron y cómo decidí especializarlo?

Trabajé sobre el Contexto A, gestión de inventario y logística. Decidí especializarlo en minimarkets y pequeños comercios que manejan productos perecibles. FreshStock resuelve el control de existencias, las entradas y salidas, las fechas de vencimiento y la identificación de productos que necesitan reposición.

### 2. ¿Cuáles son las tres funcionalidades que propuse?

La primera es la alerta de stock mínimo, que compara el stock actual con el mínimo definido para cada producto. La segunda es la alerta de vencimiento configurable, que permite consultar productos que vencerán dentro de una cantidad determinada de días. La tercera es la auditoría detallada de movimientos, que almacena el stock anterior, el stock resultante, el usuario, la fecha y el motivo.

Además, implementé una regla que impide realizar salidas superiores al stock disponible. Esta validación se ejecuta dentro de una transacción para evitar inconsistencias.

### 3. ¿Cómo nombré el sistema y por qué?

Lo llamé FreshStock porque combina la idea de productos frescos con el control de existencias. El nombre comunica que el sistema no solo cuenta unidades, sino que también ayuda a vigilar la vigencia y rotación del inventario.

## Sección 2: Mis decisiones técnicas

### 4. ¿Qué framework backend elegí y por qué?

Elegí PHP 8.2 sin framework, usando Apache y PDO. Quise demostrar que comprendo el enrutamiento, los controladores, los modelos, la validación, los códigos HTTP y el middleware sin depender de automatismos. Consideré usar Laravel, pero para el alcance de 48 horas preferí una solución pequeña que pudiera explicar línea por línea.

### 5. Dos decisiones importantes del modelado

Decidí que `movimientos_stock` guarde `stock_anterior` y `stock_resultante`. Esto permite auditar el cambio exacto y detectar inconsistencias sin reconstruir todo el historial. También decidí usar eliminación lógica para categorías y productos, porque borrar físicamente un producto rompería la interpretación histórica de sus movimientos.

La relación entre productos y categorías usa una clave foránea obligatoria. Los movimientos tienen claves foráneas hacia producto y usuario, con restricciones que evitan eliminar registros necesarios para la trazabilidad.

### 6. ¿Qué parte fue la más difícil y cómo la resolví?

La parte más difícil fue integrar Apache, rutas REST, PHP y PostgreSQL dentro de Docker. Primero corregí nombres de archivos que funcionaban en Windows pero fallaban en Linux por las mayúsculas. Después configuré `.htaccess`, habilité `mod_rewrite`, validé el JSON recibido y comprobé el flujo completo desde el navegador hasta PostgreSQL. Finalmente protegí las rutas con JWT y moví los secretos fuera del código.

## Sección 3: Mi experiencia con IA

### 7. ¿Qué herramientas utilicé?

Utilicé ChatGPT. Le asigno una utilidad de 4 sobre 5. Me ayudó a revisar comandos de Docker, explicar errores, proponer alternativas de estructura, verificar validaciones, diseñar pruebas y organizar la documentación. La herramienta fue útil como revisor, pero tuve que adaptar el código al proyecto y comprobar cada cambio ejecutándolo.

### 8. Caso en que la IA dio una respuesta inadecuada

En un momento recibí un comando de PowerShell para crear `.htaccess`, pero el nombre ya correspondía a una carpeta y el comando devolvió acceso denegado. Lo detecté al revisar el explorador de VS Code, donde `.htaccess` aparecía con una flecha de carpeta. Eliminé esa carpeta, creé un archivo real y después habilité la lectura de `.htaccess` en Apache.

También revisé la primera propuesta de JWT porque tenía un secreto escrito directamente en el controlador y un campo personalizado de expiración. Lo cambié por un secreto generado en Docker, la propiedad estándar `exp` y un middleware que valida firma, emisor, expiración y usuario activo.

### 9. Decisión importante tomada por mi cuenta

Decidí que el cliente no enviara `usuario_id` al registrar un movimiento. El backend obtiene el usuario desde el token JWT. Tomé esta decisión porque aceptar el identificador desde el formulario permitiría atribuir un movimiento a otra persona y debilitaría la auditoría.

## Sección 4: Retrospectiva

### 10. ¿Qué mejoraría con una semana adicional?

Agregaría pruebas de integración que levanten una base temporal y prueben todos los endpoints. Implementaría recuperación de contraseña y rotación o renovación de tokens. También incluiría reportes de rotación, exportación a CSV y notificaciones automáticas para productos próximos a vencer.

### 11. ¿Qué aprendí durante la prueba?

Aprendí a conectar servicios mediante la red interna de Docker, usar healthchecks y ejecutar migraciones automáticamente. También comprendí mejor cómo funciona un JWT, por qué el backend debe validar todos los datos aunque el frontend ya los valide y cómo una transacción con bloqueo `FOR UPDATE` protege el stock frente a operaciones concurrentes.

### 12. ¿Qué considero mejorable del proceso?

El enunciado es completo, pero podría indicar con mayor precisión si se espera que el archivo `.env` sea copiado manualmente antes de ejecutar Docker. Para evitar esa ambigüedad diseñé el proyecto para generar los secretos en tiempo de ejecución y permitir `docker compose up --build` sin pasos adicionales.
