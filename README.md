# Sistema de stock y personal

Aplicación PHP/MySQL para administrar productos, movimientos y existencias, además de empleados, presentismos y constancias orientativas de liquidación de sueldo.

## Funciones

- **Stock:** productos, existencias, compras, ventas, estadísticas y porcentaje de ganancia.
- **Personal:** alta, edición y baja de empleados; DNI único y cuenta de acceso individual.
- **Presentismos:** consulta mensual de asistencia y licencias. Los empleados marcan presente o ausente desde su propio portal.
- **Liquidación:** constancia PDF por empleado y período, con sueldo base, asistencia/licencias, presentismo, productividad opcional, retenciones y neto estimado.
- **Accesos:** el owner crea cuentas RRHH y administrador. Las contraseñas nuevas se almacenan con hash.

## Roles y permisos

| Rol | Permisos |
| --- | --- |
| `owner` | Todos los módulos y gestión de cuentas RRHH/administrador. |
| `rrhh` | Alta, modificación y baja de empleados; consulta de presentismos; generación de liquidaciones. Sin acceso a Stock. |
| `empleado` | Marcar y consultar únicamente su propio presentismo. No puede consultar otros empleados ni acceder a los demás módulos. |
| `administrador` | Solo Stock. |

Los permisos se validan en el servidor para cada ruta y acción POST; ocultar un enlace no concede ni revoca permisos. La carga de formularios valida CSRF. El acceso de empleado se asocia a una ficha y el servidor obtiene esa ficha desde la sesión.

## Instalación local

Requisitos: XAMPP con Apache, PHP y MySQL/MariaDB.

1. Inicia Apache y MySQL desde el panel de XAMPP.
2. En phpMyAdmin, importa `db.sql`. Crea la base `viernes` y las tablas iniciales sin borrar datos si se vuelve a ejecutar.
3. Importa `migracion_personal.sql` para aplicar roles, asociación de cuentas y tablas de personal. Es repetible.
4. Para probar, importa `datos_ejemplo.sql`. Este archivo es solo para desarrollo y también se puede volver a ejecutar.
5. Abre `http://localhost/stock-main/`.

Para una base existente, no hace falta recrearla: importa `migracion_personal.sql` y, si deseas los datos demo, `datos_ejemplo.sql`.

## Cuentas de prueba

Todas las cuentas de esta tabla usan la contraseña `123`.

| Rol | Usuario(s) | Ruta inicial |
| --- | --- | --- |
| Owner | `joaco` | Stock; puede navegar a Personal, Presentismos y Accesos. |
| RRHH | `ejemplo_rrhh` | Personal; también puede consultar Presentismos. |
| Empleado | `empleado_demo_1` a `empleado_demo_5` | Portal propio de presentismo. Cada cuenta está vinculada a un empleado distinto. |
| Administrador | `ejemplo_admin_1` a `ejemplo_admin_5` | Stock. |

Las cuentas demo, los DNI de ejemplo y la contraseña `123` son datos ficticios para probar. No importes `datos_ejemplo.sql` en producción y cambia las credenciales antes de publicar el sitio. Las cuentas reales de empleados se crean desde Personal; las cuentas RRHH/administrador, desde Accesos.

## Rutas

- `index.php`: inicio de sesión y redirección según rol.
- `stock.php`: vista de Stock, solo owner/administrador.
- `personal.php`: gestión de empleados y liquidaciones, solo owner/RRHH.
- `presentismos.php`: consulta de asistencias; solo owner/RRHH. La carga de otros empleados está reservada al owner.
- `mi_presentismo.php`: portal de la persona autenticada, solo empleado.
- `accesos.php`: creación de cuentas RRHH/administrador, solo owner.
- `logout.php`: cierre de sesión.

## Presentismo y liquidación

El portal del empleado admite una fecha no futura y un registro diario único, y solo ofrece los estados Presente/Ausente. RRHH consulta asistencias y licencias del período; owner puede registrar presentismos desde el panel.

La constancia PDF se genera desde Personal. El sueldo base proviene de la ficha del empleado; asistencia y licencias se consultan en la base; presentismo, productividad y retenciones se completan en el formulario. El sistema estima el descuento salarial según los días de ausencia marcados para afectar el sueldo y calcula el neto.

La constancia es orientativa, no un recibo legal ni una liquidación profesional. Verifica normativa, convenio, conceptos y retenciones antes de usarla para pagos. El PDF se genera localmente, sin instalar dependencias externas.

## Configuración de base remota

`conexion.php` lee estas variables de entorno:

```text
SMOKEJEANS_DB_HOST
SMOKEJEANS_DB_NAME
SMOKEJEANS_DB_USER
SMOKEJEANS_DB_PASS
```

En hosting compartido, crea primero la base desde el panel del proveedor y selecciónala en phpMyAdmin. Si el nombre no es `viernes`, adapta las instrucciones `CREATE DATABASE`/`USE viernes` de los SQL antes de importarlos, según las restricciones del proveedor. No subas contraseñas demo ni uses datos ficticios en producción.