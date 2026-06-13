# FreshStock

FreshStock es un sistema de inventario que desarrollé para minimarkets y pequeños comercios.

Permite registrar productos, categorías, entradas y salidas de stock. También muestra productos con poco stock y productos próximos a vencer.

## Contexto

Para esta prueba trabajé con el contexto de gestión de inventario y logística.

Elegí un minimarket porque necesita controlar productos, ventas, compras, stock y fechas de vencimiento.

## Funciones principales

* Registro de usuarios.
* Inicio de sesión con JWT.
* Contraseñas protegidas con bcrypt.
* CRUD de categorías.
* CRUD de productos.
* Búsqueda y paginación.
* Entradas y salidas de stock.
* Historial de movimientos.
* Alertas de stock mínimo.
* Alertas de vencimiento.
* Roles de administrador y operador.
* Interfaz con HTML, CSS y JavaScript.

## Funciones adicionales

### Stock mínimo

El sistema muestra los productos cuyo stock actual es menor o igual al stock mínimo.

### Productos por vencer

Permite consultar los productos que están cerca de su fecha de vencimiento.

### Historial

Cada movimiento guarda:

* Producto.
* Usuario.
* Cantidad.
* Tipo de movimiento.
* Motivo.
* Stock anterior.
* Stock resultante.
* Fecha.

### Control de stock

El sistema no permite retirar una cantidad mayor al stock disponible.

### Eliminación lógica

Los productos y categorías se desactivan en lugar de eliminarse completamente. Esto ayuda a conservar el historial.

## Tecnologías

* PHP 8.2.
* Apache.
* PostgreSQL 15.
* PDO.
* JWT.
* bcrypt.
* HTML.
* CSS.
* JavaScript.
* Docker.
* Docker Compose.
* Swagger.
* Git y GitHub.

## Decisiones técnicas

Elegí PHP porque es un lenguaje que estoy aprendiendo y quería practicar la creación de una API REST.

Usé PDO para conectarme con PostgreSQL mediante consultas preparadas.

Elegí PostgreSQL porque permite trabajar con claves foráneas, relaciones, índices y transacciones.

Para el frontend usé HTML, CSS y JavaScript porque la prueba requería una interfaz básica y funcional.

Utilicé Docker para ejecutar el backend y la base de datos con un solo comando.

## Estructura

```text
taskflow-yosep-eusebio/
├── controlador/
├── middleware/
├── modelo/
├── tests/
├── util/
├── vista/
├── docs/
├── Dockerfile
├── docker-compose.yml
├── index.php
├── index.html
├── migraciones.sql
├── seeds.sql
├── README.md
└── REFLEXION.md
```

## Funcionamiento de Docker

El proyecto utiliza dos contenedores.

El primer contenedor ejecuta PHP y Apache.

El segundo contenedor ejecuta PostgreSQL.

PHP se conecta a PostgreSQL mediante la red interna de Docker.

También se utiliza un volumen para conservar los datos de la base de datos.

## Requisitos

* Docker Desktop.
* Docker Compose.
* Puerto 8080 disponible.

## Instalación

```bash
git clone <URL_DEL_REPOSITORIO>
cd taskflow-yosep-eusebio
docker compose up -d --build
```

Abrir la aplicación:

```text
http://localhost:8080
```

Estado de la API:

```text
http://localhost:8080/health
```

Documentación:

```text
http://localhost:8080/docs/
```

## Comandos

Ver contenedores:

```bash
docker compose ps
```

Ver logs:

```bash
docker compose logs -f servidor_web
```

Detener el proyecto:

```bash
docker compose down
```

Eliminar contenedores y datos:

```bash
docker compose down -v
```

## Usuarios de prueba

Administrador:

```text
Correo: admin@freshstock.local
Contraseña: Admin123*
```

Operador:

```text
Correo: operador@freshstock.local
Contraseña: Operador123*
```

Estas cuentas son solamente para realizar pruebas.

## Endpoints principales

| Método | Ruta                    | Función              |
| ------ | ----------------------- | -------------------- |
| GET    | `/health`               | Estado de la API     |
| POST   | `/auth/register`        | Registrar usuario    |
| POST   | `/auth/login`           | Iniciar sesión       |
| GET    | `/api/categories`       | Listar categorías    |
| POST   | `/api/categories`       | Crear categoría      |
| PUT    | `/api/categories/{id}`  | Actualizar categoría |
| DELETE | `/api/categories/{id}`  | Desactivar categoría |
| GET    | `/api/products`         | Listar productos     |
| POST   | `/api/products`         | Crear producto       |
| PUT    | `/api/products/{id}`    | Actualizar producto  |
| DELETE | `/api/products/{id}`    | Desactivar producto  |
| GET    | `/api/stock/movements`  | Ver movimientos      |
| POST   | `/api/stock/movements`  | Registrar movimiento |
| GET    | `/api/alerts/low-stock` | Ver stock mínimo     |
| GET    | `/api/alerts/expiring`  | Ver vencimientos     |

Las rutas `/api` necesitan un token:

```http
Authorization: Bearer TOKEN
```

## Ejemplo de login

```json
{
  "correo": "admin@freshstock.local",
  "contrasena": "Admin123*"
}
```

## Ejemplo de producto

```json
{
  "categoria_id": 1,
  "nombre": "Atún en lata",
  "codigo_barras": "775100000010",
  "precio": 6.50,
  "stock_actual": 20,
  "stock_minimo": 5,
  "fecha_vencimiento": "2027-01-31"
}
```

## Ejemplo de movimiento

```json
{
  "producto_id": 1,
  "cantidad": 3,
  "tipo_movimiento": "SALIDA",
  "motivo": "Venta"
}
```

## Base de datos

El sistema tiene estas tablas:

* usuarios.
* categorias.
* productos.
* movimientos_stock.

Una categoría puede tener varios productos.

Un producto puede tener varios movimientos.

Un usuario puede registrar varios movimientos.

El historial utiliza `INNER JOIN` para mostrar el producto y el usuario responsable.

## Seguridad

* Contraseñas con bcrypt.
* Autenticación con JWT.
* Token con tiempo de expiración.
* Consultas preparadas.
* Validación de datos.
* Control de roles.
* Variables de entorno.
* Bloqueo de stock negativo.
* Errores sin información sensible.

## Pruebas

```bash
docker compose exec servidor_web php tests/run.php
```

Las pruebas verifican JWT, validaciones y control de stock.

## Uso de inteligencia artificial

Durante el desarrollo utilicé ChatGPT como apoyo para comprender Docker, revisar errores de PHP, JWT, SQL y documentación.

Fui probando los cambios y corrigiendo los errores que aparecieron.

Por ejemplo, inicialmente creé `.htaccess` como una carpeta. Después lo eliminé y lo creé correctamente como archivo.

También tuve un conflicto porque el puerto 8080 estaba ocupado por otro contenedor. Detuve el contenedor anterior y volví a iniciar el proyecto.

## Mejoras futuras

Con más tiempo agregaría:

* Recuperación de contraseña.
* Registro de proveedores.
* Reportes en PDF o Excel.
* Más pruebas.
* Notificaciones de vencimiento.
* Despliegue público.
