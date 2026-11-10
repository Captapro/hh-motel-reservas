/**
 * Receptor de respaldos de HH Motel.
 * Recibe el respaldo de la base desde el sistema, lo guarda en una carpeta de tu Drive
 * y deja solo los últimos 14 (borra los más antiguos a la papelera).
 *
 * Propiedades del script (Configuración del proyecto → Propiedades del script):
 *   TOKEN      clave larga y secreta (la misma que se pone en Render como BACKUP_DRIVE_TOKEN)
 *   FOLDER_ID  id de la carpeta de Drive donde se guardan (lo que va después de /folders/ en su URL)
 */
var CONSERVAR = 14;

function doPost(e) {
  try {
    var props = PropertiesService.getScriptProperties();
    var body = JSON.parse(e.postData.contents);

    var esperado = props.getProperty('TOKEN');
    if (!esperado || !body.token || !iguales(String(body.token), esperado)) {
      return salida({ ok: false, error: 'no autorizado' });
    }
    if (!body.name || !/^hh-motel-[0-9_-]+\.dump$/.test(body.name) || !body.data) {
      return salida({ ok: false, error: 'datos inválidos' });
    }

    var carpeta = DriveApp.getFolderById(props.getProperty('FOLDER_ID'));
    var bytes = Utilities.base64Decode(body.data);
    var archivo = carpeta.createFile(Utilities.newBlob(bytes, 'application/octet-stream', body.name));

    // Solo se conservan los últimos CONSERVAR respaldos; el resto va a la papelera.
    var lista = [];
    var it = carpeta.getFiles();
    while (it.hasNext()) {
      var f = it.next();
      if (/^hh-motel-.*\.dump$/.test(f.getName())) lista.push(f);
    }
    lista.sort(function (a, b) { return b.getName() < a.getName() ? -1 : 1; }); // más nuevos primero
    for (var i = CONSERVAR; i < lista.length; i++) lista[i].setTrashed(true);

    return salida({ ok: true, size: archivo.getSize(), name: body.name, guardados: Math.min(lista.length, CONSERVAR) });
  } catch (err) {
    return salida({ ok: false, error: String(err) });
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
