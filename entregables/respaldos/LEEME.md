# Respaldo diario de la base a Google Drive

Cada día, pasadas las 05:00 (hora de Chile), el sistema respalda toda la base de datos y
la sube a una carpeta de tu Drive. Se conservan los últimos 14 respaldos. Si falla 3 veces
en el día, llega una alerta a la campanita y al celular de administración.

## Instalación (una sola vez, ~5 minutos)

1. En Drive, crea una carpeta **Respaldos HH Motel**. Ábrela y copia el id que está al final
   de la URL (`drive.google.com/drive/folders/ESTE_ES_EL_ID`).
2. Entra a **script.google.com → Proyecto nuevo**. Borra lo que trae y pega todo el
   contenido de `Codigo.gs`. Ponle de nombre "Respaldos HH".
3. Ve a **Configuración del proyecto (engranaje) → Propiedades del script → Agregar**:
   - `TOKEN` = una clave larga inventada por ti (30 letras y números al azar).
   - `FOLDER_ID` = el id del paso 1.
4. **Implementar → Nueva implementación → Tipo: Aplicación web**
   - Ejecutar como: **Yo**
   - Quién tiene acceso: **Cualquier persona**
   - Implementar → Autorizar acceso (Google avisa que la app no está verificada: es tu propio
     script; entra en "Configuración avanzada" y continúa).
   - Copia la **URL de la aplicación web** (termina en `/exec`).
5. En **Render → hh-motel-reservas → Environment** agrega:
   - `BACKUP_DRIVE_URL` = la URL del paso 4
   - `BACKUP_DRIVE_TOKEN` = la misma clave del paso 3
   (La URL y la clave son secretas: no las compartas por chat ni correo.)
6. Render hace un deploy solo. Al día siguiente (o antes, si pides un respaldo manual) aparece el
   primer archivo en la carpeta.

## Cómo comprobar que funciona
- En la carpeta de Drive aparecen archivos `hh-motel-AAAA-MM-DD_HHMMSS.dump`.
- Cada archivo pesa unos pocos MB y se ve con la fecha de hoy.

## Cómo restaurar (si algún día hace falta)
Los archivos `.dump` se restauran con `pg_restore` (Postgres 16) sobre una base vacía:
`pg_restore --no-owner --dbname=URL_DE_LA_BASE_NUEVA archivo.dump`
No restaures encima de la base en uso sin avisar: pídeme que lo haga y primero hago una copia nueva.
