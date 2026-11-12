# Ventas a Google Ads desde el servidor

Cada reserva del catálogo que llegó por un anuncio de Google (tiene `gclid`, `gbraid` o `wbraid`) y quedó
**pagada** se anota, una sola vez, en una hoja de Google. Google Ads importa esa hoja solo. Así la venta
cuenta aunque el cliente pague y cierre la página (la etiqueta del navegador solo cuenta si vuelve).

Flujo: sistema (`app:google-ventas`, cada 10 min) → Apps Script (`Codigo.gs`) → hoja "Ventas" → Google Ads (carga programada).

## Puesta en marcha (una vez)

1. **Hoja:** crea una hoja de cálculo nueva ("Ventas Google HH") en la cuenta de Google que administra Google Ads.
   Copia su id (lo que va entre `/d/` y `/edit`).
2. **Script:** en esa hoja, Extensiones → Apps Script. Pega `Codigo.gs`. En Configuración del proyecto →
   Propiedades del script agrega `TOKEN` (clave larga al azar) y `SHEET_ID` (el id de la hoja).
   Implementar → Nueva implementación → Aplicación web → ejecutar como "yo", acceso "cualquier persona". Copia la URL.
3. **Render:** variables `GOOGLE_CONVERSIONS_URL` (la URL del paso 2) y `GOOGLE_CONVERSIONS_TOKEN` (la misma clave).
   Opcional: `GOOGLE_CONVERSIONS_ACTION` si el nombre de la conversión no es "Reserva pagada (servidor)".
4. **Google Ads → Objetivos → Conversiones → Nueva:** categoría Compra, fuente "Conectar la fuente de datos más adelante"
   (sin conexión), nombre exacto "Reserva pagada (servidor)", principal, valor distinto por conversión, recuento "Todas".
5. **Google Ads → Herramientas → Administrador de datos → Conectar producto → Google Sheets → Conexión directa**
   (sin conversiones avanzadas): datos de **Conversiones** → medir conversiones **sin conexión** → acción
   "Reserva pagada (servidor)". Autoriza con la cuenta dueña de la hoja y elige la hoja y su primera pestaña.
   Google asigna solo `gclid`, `conversion_time`, `order_id`, `gbraid`, `wbraid`, valor y moneda. Se ejecuta cada día.
   La pestaña "Ventas" debe ser la PRIMERA de la hoja y los encabezados van en la fila 1 (sin otra línea encima).

## Nota sobre contar dos veces
La etiqueta del navegador ("Reserva pagada") y la importación ("Reserva pagada (servidor)") pueden contar la misma
venta. Recomendado: dejar la del servidor como **principal** y la de la etiqueta como **secundaria** (solo observación).

## Qué vigilar
- Si falla seguido (≈1 hora), llega una alerta a la campanita de administración.
- El historial queda en el registro de auditoría (`sistema.google_ventas` y `sistema.google_ventas_fallo`).
- Google acepta clics de hasta 90 días, y la hora de la venta debe ser posterior al clic.
