# 📋 Guía de Instalación en Infinityfree

## Paso 1: Preparar el repositorio localmente

```bash
git clone https://github.com/joacopiolapiola/stock.git
cd stock
```

## Paso 2: Configurar variables de entorno

1. Copia el archivo `.env.example` a `.env`:
```bash
cp .env.example .env
```

2. Edita `.env` con tus credenciales de Infinityfree:
```
SMOKEJEANS_DB_HOST=localhost
SMOKEJEANS_DB_NAME=nombre_base_datos
SMOKEJEANS_DB_USER=usuario_infinityfree
SMOKEJEANS_DB_PASS=contraseña
```

⚠️ **IMPORTANTE:** `.env` NO se subirá a GitHub (está en `.gitignore`)

## Paso 3: Crear la base de datos en Infinityfree

1. Accede al panel de Infinityfree
2. Ve a **MySQL Databases**
3. Crea una nueva base de datos
4. Importa `db.sql`:
   - phpMyAdmin → Importar → Selecciona `db.sql`

## Paso 4: Subir archivos a Infinityfree

### Opción A: Git (Recomendado)
```bash
# En Infinityfree, sigue estos pasos:
# 1. Conecta tu repositorio privado
# 2. Deploy automático desde GitHub
```

### Opción B: FTP (Manual)
1. Descarga FileZilla o similar
2. Conecta con los datos de FTP de Infinityfree
3. Sube todos los archivos a la carpeta `public_html/`
4. Crea manualmente el archivo `.env` con tus credenciales

## Paso 5: Configurar permisos

En Infinityfree, algunos directorios necesitan permisos especiales:

```bash
chmod 755 public_html/
chmod 644 public_html/*.php
chmod 644 public_html/*.css
```

## Paso 6: Verificar instalación

1. Accede a tu dominio en el navegador
2. Comprueba que `index.php` carga correctamente
3. Verifica conexión a base de datos

## 🔒 Seguridad

- ✅ `.env` está en `.gitignore` (no se sube a GitHub)
- ✅ `.htaccess` bloquea acceso a archivos sensibles
- ✅ Credenciales protegidas en Infinityfree

## 📝 Notas

- El repositorio es **PRIVADO** en GitHub
- Solo TÚ puedes acceder al código
- Las credenciales se configuran localmente en cada servidor

## ❓ Solución de problemas

**Error de conexión a BD:**
- Verifica credenciales en `.env`
- Asegúrate de que la BD está creada en Infinityfree

**Página en blanco:**
- Revisa los logs de Infinityfree
- Verifica que PHP está habilitado

**Permisos denegados:**
- Aumenta permisos en carpetas (chmod 755)
