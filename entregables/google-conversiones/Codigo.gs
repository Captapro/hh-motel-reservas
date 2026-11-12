/**
 * Receptor de ventas para Google Ads de HH Motel.
 * Recibe desde el sistema cada reserva pagada que llegó por un anuncio de Google y la anota en
 * la pestaña "Ventas" de una hoja de cálculo. Google Ads (Administrador de datos → Google Sheets)
 * lee esa pestaña, que debe ser la PRIMERA de la hoja y tener los encabezados en la fila 1.
 *
 * Propiedades del script (Configuración del proyecto → Propiedades del script):
 *   TOKEN     clave larga y secreta (la misma que se pone en Render como GOOGLE_CONVERSIONS_TOKEN)
 *   SHEET_ID  id de la hoja de cálculo (lo que va entre /d/ y /edit en su URL)
 */
var HOJA = 'Ventas';
var ENCABEZADOS = [
  'gclid', 'conversion_time', 'conversion_value', 'conversion_currency', 'gbraid', 'wbraid', 'order_id'
];
var COL_ORDEN = 7; // "order_id": con él una venta nunca se anota dos veces

function doPost(e) {
  var lock = LockService.getScriptLock();
  try {
    lock.waitLock(20000);
    var props = PropertiesService.getScriptProperties();
    var body = JSON.parse(e.postData.contents);

    var esperado = props.getProperty('TOKEN');
    if (!esperado || !body.token || !iguales(String(body.token), esperado)) {
      return salida({ ok: false, error: 'no autorizado' });
    }

    var libro = SpreadsheetApp.openById(props.getProperty('SHEET_ID'));
    var hoja = libro.getSheetByName(HOJA) || libro.insertSheet(HOJA, 0);

    // Encabezados en la fila 1 (formato que lee Google Ads).
    if (hoja.getLastRow() < 1) {
      hoja.getRange(1, 1, 1, ENCABEZADOS.length).setValues([ENCABEZADOS]);
    }

    if (!body.rows || !body.rows.length) {
      return salida({ ok: true, recibidos: [], nuevas: 0 });
    }

    var existentes = {};
    var ultima = hoja.getLastRow();
    if (ultima >= 2) {
      hoja.getRange(2, COL_ORDEN, ultima - 1, 1).getValues().forEach(function (f) { existentes[String(f[0])] = true; });
    }

    var nuevas = [];
    var recibidos = [];
    body.rows.forEach(function (r) {
      var orden = String(r.order_id || '');
      if (!orden || !/^[A-Za-z0-9_-]+$/.test(orden)) return;
      recibidos.push(orden);
      if (existentes[orden]) return;
      existentes[orden] = true;
      nuevas.push([
        String(r.gclid || ''), String(r.conversion_time || ''), Number(r.value) || 0, String(r.currency || 'CLP'),
        String(r.gbraid || ''), String(r.wbraid || ''), orden
      ]);
    });

    if (nuevas.length) {
      var inicio = hoja.getLastRow() + 1;
      // La hora va como texto para que Sheets no la reformatee.
      hoja.getRange(inicio, 2, nuevas.length, 1).setNumberFormat('@');
      hoja.getRange(inicio, 1, nuevas.length, ENCABEZADOS.length).setValues(nuevas);
    }

    return salida({ ok: true, recibidos: recibidos, nuevas: nuevas.length });
  } catch (err) {
    return salida({ ok: false, error: String(err) });
  } finally {
    try { lock.releaseLock(); } catch (x) {}
  }
}

function doGet() {
  return salida({ ok: false, error: 'solo POST' });
}

function salida(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

// Comparación sin cortarse en el primer carácter distinto.
function iguales(a, b) {
  if (a.length !== b.length) return false;
  var r = 0;
  for (var i = 0; i < a.length; i++) r |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return r === 0;
}
