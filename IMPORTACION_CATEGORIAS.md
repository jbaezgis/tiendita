# Importación de Categorías de Usuarios

Esta funcionalidad permite actualizar automáticamente las categorías de los usuarios importando desde un archivo Excel.

## Características

- ✅ Importación desde archivos Excel (.xlsx, .xls) y CSV
- ✅ Validación de datos antes de la importación
- ✅ Reporte detallado de errores y éxitos
- ✅ Interfaz web intuitiva con subida de archivos
- ✅ Descarga de plantilla de ejemplo
- ✅ Comando de consola para importaciones masivas
- ✅ Manejo automático de formatos de cédula (con y sin guiones)

## Formato del Archivo

El archivo debe contener las siguientes columnas:

| Columna | Descripción | Ejemplo |
|---------|-------------|---------|
| `cedula` | Cédula del usuario (con o sin guiones) | `001-0000444-9` o `00100004449` |
| `category` | Código de la categoría | `grupo-001` |

### Ejemplo de contenido:

```csv
cedula,category
001-0000444-9,grupo-001
123-4567890-1,grupo-002
987-6543210-9,grupo-003
```

### ⚠️ **Nota importante sobre cédulas:**

El sistema maneja automáticamente ambos formatos de cédula:
- **Con guiones**: `001-0000444-9` (formato típico en Excel)
- **Sin guiones**: `00100004449` (formato almacenado en la base de datos)

El sistema convertirá automáticamente el formato con guiones al formato sin guiones para la búsqueda en la base de datos.

## Uso desde la Interfaz Web

1. **Acceder a la gestión de usuarios**
   - Navega a la sección de usuarios en el panel de administración

2. **Descargar plantilla (opcional)**
   - Haz clic en "Importar Categorías"
   - En el modal, haz clic en "Descargar Plantilla"
   - Completa la plantilla con los datos de tu empresa

3. **Subir archivo**
   - Haz clic en "Importar Categorías"
   - Selecciona tu archivo Excel o CSV
   - Haz clic en "Importar Categorías"

4. **Revisar resultados**
   - El sistema mostrará un resumen de la importación
   - Se actualizarán automáticamente las categorías de los usuarios

## Uso desde Consola

### Comando de importación:

```bash
php artisan users:import-categories /ruta/al/archivo.xlsx
```

### Generar plantilla de ejemplo:

```bash
php artisan users:generate-category-template
```

### Ejemplos de uso:

```bash
# Importar desde archivo Excel
php artisan users:import-categories /home/user/categorias.xlsx

# Importar desde archivo CSV
php artisan users:import-categories /home/user/categorias.csv

# Generar plantilla con nombre personalizado
php artisan users:generate-category-template --output=mi_plantilla.xlsx
```

## Validaciones

El sistema realiza las siguientes validaciones:

- ✅ **Existencia de usuarios**: Solo actualiza usuarios que existan en el sistema
- ✅ **Existencia de categorías**: Solo asigna categorías que existan en el sistema
- ✅ **Formato de datos**: Valida que las columnas requeridas estén presentes
- ✅ **Datos no vacíos**: Verifica que los campos no estén vacíos
- ✅ **Tamaño de archivo**: Máximo 2MB por archivo
- ✅ **Formato de cédula**: Maneja automáticamente cédulas con y sin guiones

## Reportes

### Éxitos:
- Número de usuarios actualizados
- Lista de usuarios actualizados con sus nuevas categorías

### Errores:
- Usuarios no encontrados
- Categorías no encontradas
- Filas con datos inválidos
- Errores de formato

## Archivos Creados

### Comandos:
- `app/Console/Commands/ImportUserCategories.php` - Comando principal de importación
- `app/Console/Commands/GenerateCategoryTemplate.php` - Generador de plantillas

### Controlador:
- `app/Http/Controllers/UserController.php` - Manejo de descargas de plantillas

### Modificaciones:
- `resources/views/livewire/users/index.blade.php` - Interfaz de importación
- `routes/web.php` - Ruta para descarga de plantillas

## Consideraciones Importantes

1. **Backup**: Siempre haz un backup de la base de datos antes de importaciones masivas
2. **Pruebas**: Prueba primero con un archivo pequeño
3. **Categorías**: Asegúrate de que las categorías existan antes de importar
4. **Cédulas**: Verifica que las cédulas estén correctamente formateadas
5. **Permisos**: Solo usuarios con permisos de administrador pueden usar esta funcionalidad

## Solución de Problemas

### Error: "File not found"
- Verifica que la ruta del archivo sea correcta
- Asegúrate de que el archivo exista y sea accesible

### Error: "No data found in the Excel file"
- Verifica que el archivo contenga datos
- Asegúrate de que tenga las columnas correctas

### Error: "User with cedula 'xxx' not found"
- Verifica que el usuario exista en el sistema
- Revisa el formato de la cédula (el sistema maneja automáticamente cédulas con y sin guiones)
- Ejemplo: `001-0000444-9` y `00100004449` son tratados como la misma cédula

### Error: "Category with code 'xxx' not found"
- Verifica que la categoría exista en el sistema
- Revisa el código de la categoría

## Soporte

Para reportar problemas o solicitar mejoras, contacta al equipo de desarrollo. 