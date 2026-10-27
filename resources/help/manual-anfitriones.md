# Manual del sistema HH Motel para anfitriones

Los textos entre comillas son los nombres exactos de botones, pantallas y mensajes. "Playroom" y "habitación" son lo mismo.

## 0. Navegación
- Barra superior: "Tablero", "Buscar reserva", "Caja", "Aseo del día", "Mantención", "Clientes" (según permisos del perfil). A la derecha: chip de tarifa ("<TARIFA> cierra en Xh Ym" o "FUERA DE HORARIO abre en …"), botones "+ Ingreso presencial" y "+ Reserva", tu nombre y "Salir".
- Riel izquierdo con íconos: Tablero, Calendario, Clientes, Productos, Caja, Operaciones, Ventas, Analytics, Claves, Opiniones. Solo aparecen los que tu perfil tiene habilitados.
- Botón naranjo "Ayuda" abajo a la derecha: este asistente.

## 1. Tablero ("HH MOTEL — Tablero de Playrooms")
- Se recarga solo cada 30 segundos ("actualizar ahora" para forzarlo). No se recarga mientras el chat de Ayuda está abierto.
- Herramientas: "Ver catálogo ↗", "Copiar link del catálogo", filtro "Todas las alas" / "Ala norte" / "Ala sur", botón de vista ("Vista compacta" / "Vista chips" / "Vista normal") y el QR "Validador de tickets" (escanéalo con el celular para abrir la cámara que lee los pases).
- Avisos en vivo: "📲 Pase escaneado — <cliente>" con "Cobrar y hacer check-in →" o "Hacer check-in →"; "💳 Pago recibido — <cliente> · $monto" (pago por Mercado Pago) con "Ver reserva →".
- "⚠ Pendientes de gestionar": reservas que todavía no tienen ningún pago. Detalle completo en la sección 1.1.
- Contadores: Ocupadas, En aseo, Disponibles, Por llegar, Fuera de servicio. Resumen del día: Playrooms vendidos hoy, Extras vendidos hoy, Venta Playrooms, Venta extras, Total del día (solo estadías de hoy pagadas completas).
- Secciones:
  - **Ocupadas**: con check-in y sin check-out. Muestra "Libera en X" o en rojo "Pasó su horario hace X". Botones "+ Agregar consumo →", "Falta cobrar $X →" (si hay saldo) o "Finalizar / Check-out →" (sin saldo). "RESERVADA — SIN CHECK-IN" con "Falta check-in — marcar ahora" si falta el check-in. Estadía corta: "⏱ Estadía corta · sale al cierre, HH:MM".
  - **Disponibles**: libres. Filtros por categoría, "Con reservas futuras" y "Ala norte" / "Ala sur" (es el mismo filtro de ala de arriba; apretarlo de nuevo vuelve a todas las alas). Botones "Ingresar →" (ingreso presencial con esa habitación) y "o reservar para más tarde".
  - **Por llegar**: libres ahora pero con una reserva próxima: "no las asignes a clientes sin reserva". Muestra "llega en X", "llegando ahora" o "atrasada X — sin check-in", y el botón "Marcar check-in".
  - **En aseo**: no vuelven a Disponibles hasta que recepción marque aseo listo (ver sección 8).
  - **Fuera de servicio**: en mantención o inactivas.
- En cada tarjeta: "N reservas →", "📋 Copiar link de ficha" y "Cambiar estado" (Activa, En mantención, Inactiva, Aseo; "Aseo" solo si está libre) con "Guardar". Badge "Mantención" si la última inspección tuvo una falla; badge "OFERTA" o "DESCUENTO X%" si hay oferta vigente.

## 1.1 "⚠ Pendientes de gestionar"
Recuadro arriba del tablero con las reservas que **todavía no tienen ningún pago**. Si no hay ninguna, no aparece. Hay que atenderlas: cobrar, mantenerlas o cancelarlas.

Aparece en tres casos:
1. **Reserva en línea esperando el pago**: el cliente reservó en el catálogo y tiene 10 minutos para pagar con Mercado Pago. Se ve con "⏳ vence en X min". Si no paga a tiempo, se libera sola y desaparece.
2. **Reserva hecha en recepción con "+ Reserva" sin cobrar**: se ve con "Sin vencimiento". No se libera sola: espera a que el cliente pague o a que alguien la cancele.
3. **Reserva en línea que recepción mantuvo** con "Pagará en recepción" o "Pago tardío": también "Sin vencimiento".

Cómo leer cada fila:
- **Código** (HH-AAAAMMDD-NNNNN) y **nombre del cliente**.
- **Habitación · categoría · "llegó hace X"**: "llegó hace" es el tiempo desde que se **creó la reserva**, no desde que llegó el cliente.
- **Fecha y hora** a la que está reservada ("hoy 23:00" o "27/10 01:45").
- **"⏳ vence en X"** (retención en línea de 10 minutos) o **"Sin vencimiento"**.
- **"Sin contactar"**: todavía no se le enviaron los datos de pago. Cambia a **"✓ Ya se le avisó"** al usar "Copiar texto", "Copiar datos de cuenta" o el link de Mercado Pago en la ficha.
- **Color**: normal al principio, amarilla a los 3 minutos y roja a los 5. Rojo significa que nadie la ha atendido.
- Clic en la fila (o la flecha →) abre la ficha de la reserva.

Qué hacer:
- Si el cliente va a pagar a distancia: en la ficha, envía los datos de pago o el link de Mercado Pago (sección 3).
- Si es una reserva en línea con "⏳ vence en…" y el cliente avisa que pagará al llegar: "Pagará en recepción" (o "Pago tardío" si pagó fuera de plazo) para que no se libere.
- Si el cliente llegó: "Registrar pago" y después el check-in. Sin la habitación pagada no hay check-in.
- Si el cliente no viene: "Cancelar" en la ficha (la habitación queda libre).
- Sale del recuadro sola con el primer pago (la reserva pasa a confirmada), o si se cancela o vence.

## 2. Crear una reserva o un ingreso presencial

**Diferencia entre los dos botones de la barra superior:**

| | "+ Ingreso presencial" | "+ Reserva" |
|---|---|---|
| Cuándo | El cliente **está en recepción** y entra **ahora** | El cliente quiere una hora **más adelante** (llamó, escribió, o vendrá después) |
| Fecha y hora | No se eligen: entra ahora | Se eligen fecha y hora |
| Acompañantes | Se registran en el formulario | Se registran en el check-in, al llegar |
| Productos y combos | Se ofrecen en el formulario (paso obligatorio) y aparece el aviso de upsells | Se ofrecen en el check-in |
| Pago | Se cobra en el mismo flujo | Queda sin pago hasta que pague (a distancia o al llegar) |
| Estado al crear | Confirmada | Pendiente de pago, en "⚠ Pendientes de gestionar" con "Sin vencimiento" |
| Botón final | "Crear, cobrar y hacer check-in →" | "Crear reserva" |
| Después | Registrar pago → check-in → la habitación queda Ocupada | Vuelve al tablero; la habitación aparece en "Por llegar" cuando se acerca la hora |

Regla práctica: si el cliente está frente a ti y entra ya, usa "+ Ingreso presencial". Si no está o entra más tarde, usa "+ Reserva". No uses "+ Reserva" para alguien que entra ahora: quedaría como pendiente y tendrías que hacer el cobro y el check-in por separado.

- Desde una tarjeta de Disponibles: "Ingresar →" abre el ingreso presencial con esa habitación, y "o reservar para más tarde" abre "+ Reserva" con esa habitación.
- Cada pantalla tiene un atajo a la otra: "¿Es para más tarde? Hacer una reserva" y "¿El cliente está aquí? Ingreso presencial".

Pasos:
1. **La estadía**: elige "Habitación" (cada opción dice "Libre ahora" u "Ocupada, libera en X min"), "Fecha" y "Hora" (solo en Reserva), "Duración" (solo las que tienen precio en esa categoría) y "Personas". Horario de venta: lun a jue 10:30 a 03:00; vie 10:30 a dom 22:30 corrido. Fuera de eso el sistema no deja crear la reserva.
2. **El cliente**: pega el "Teléfono / WhatsApp" (es la identidad del cliente). Si existe, aparece "✓ Datos cargados" con su historial y "✎ Editar datos". Si no, "Cliente nuevo": completa "Nombre", "Apellido", "Email" y el "Documento de identidad" (RUT o Pasaporte; el RUT se valida solo). Nacionalidad y fecha de nacimiento son opcionales (la fecha se necesita para cupones con edad mínima).
   - En Ingreso presencial también se cargan los "Acompañantes" (nombre y apellido obligatorios, RUT opcional), uno por cada persona extra.
3. **Ofrecer productos y combos** (solo Ingreso presencial): hay que resolverlo con "El cliente no quiere nada · continuar →" o "Continuar con N ítems →".
4. **Resumen**: revisa "Precio habitación", descuentos y "Total a pagar". "Código promocional (opcional)" para cupones. Si la habitación ya tiene una oferta automática, el cupón no se suma ("El código no aplica — esta habitación ya está en oferta.").
5. Botón "Crear reserva" o, en ingreso, "Crear, cobrar y hacer check-in →". En ingreso presencial, si no cargaste consumo, aparece "💡 ¿Le ofreciste algo al cliente?" con upsells (subir de categoría, más tiempo): "Agregar lo elegido y crear reserva" o "El cliente no quiere nada — crear igual".
6. Si queda saldo, el sistema te lleva a "Registrar pago" (sección 7). En ingreso presencial, después sigue el check-in.

**Estadía corta por cierre**: si la hora más la duración mínima pasa del cierre y quedan entre 30 y 90 minutos (valores por defecto), aparece "⏱ Estadía corta por cierre" con precio proporcional y salida al cierre. Hay que marcar "El cliente acepta salir a las HH:MM". No suma ofertas ni cupones y no admite tiempo extra.

Errores comunes:
- "Cliente bloqueado en lista negra. Revisar con administración antes de reservar." → no se vende; avisa al administrador.
- "No se puede crear una reserva con fecha y hora en el pasado."
- "La habitación X ya no está disponible en ese horario." → elige otra habitación u hora.
- "La habitación X admite como máximo N personas."
- "El horario solicitado no corresponde a ninguna tarifa vigente." → está fuera de horario.

## 3. Reservas en línea (catálogo) y cobro a distancia
- Una reserva hecha por el cliente en el catálogo queda retenida 10 minutos esperando el pago. Si no paga, vence sola y se libera la habitación.
- Aparece en "⚠ Pendientes de gestionar". En la ficha: "⏳ Retenida hasta las HH:MM" con los botones "Pagará en recepción" o "Pago tardío": cualquiera de los dos quita el vencimiento.
- Una reserva en línea normalmente llega pagada. Si quedó mantenida sin pago ("Pagará en recepción" o "Pago tardío"), al llegar el cliente se cobra primero y después se hace el check-in.
- Para enviarle cómo pagar (en la ficha, si hay saldo): "Copiar datos de cuenta", "Copiar texto" (mensaje completo con código, horario, monto y enlace al pase) y "Contactar por WhatsApp" (abre el chat; pegas el mensaje). Copiar marca "✓ Ya se le avisó".
- Cobro por link: bloque "Cobrar por link (Mercado Pago)" con "Copiar mensaje" y "Enviar por WhatsApp". Cuando el cliente paga, la reserva se confirma sola y aparece "💳 Pago recibido" en el tablero. Si dice "revisa esta reserva: pagó fuera de tiempo o de más", avisa al administrador.
- El correo de confirmación lo manda el sistema solo; los WhatsApp los envía el anfitrión a mano.

## 4. Buscar y la ficha de la reserva
- "Buscar reserva": por código, nombre o teléfono. También: "Calendario" (Mes, Semana, Día, Historial) y "Próximas reservas".
- Ficha "Reserva HH-…": estado, datos, dinero ("Precio final", "Consumo", "Pagado", "Saldo") y "Pagos registrados".
- Botones de la ficha:
  - "Ver pase PDF" / "Descargar pase" (antes del check-in).
  - "Modificar": cambia habitación, duración, fecha, hora o personas; botón "Recalcular y guardar cambios". El precio se recalcula.
  - "Marcar check-in — ofrecer consumo →".
  - "Registrar pago (abono o total) →".
  - "Finalizar reserva →" (con check-in y sin saldo; si hay saldo aparece "Finalizar · falta cobrar $X").
  - "Cancelar" (solo antes del check-in): "Motivo (opcional)" y "Confirmar cancelación". La habitación queda libre al instante. Cancelar NO devuelve dinero: las devoluciones las hace solo el administrador. Con check-in no se puede cancelar: se usa "Finalizar".
  - "✎ Corregir" en un pago para completar o corregir N° de voucher o N° de boleta.
  - "Pedir reseña por WhatsApp" (reservas finalizadas).
- No existe botón de "no llegó" (no show). Una reserva atrasada sigue en "Por llegar" como "atrasada X — sin check-in" hasta que se cancela.

## 5. Check-in
Formas de iniciarlo:
1. El cliente muestra el QR de su pase: escanea el "Validador de tickets" del tablero con tu celular y apunta al pase. Aparece "Pase válido" con cliente, habitación, horario y saldo, y en el computador el aviso "📲 Pase escaneado". Si no hay computador cerca: "No tengo computador cerca: hacerlo desde este celular".
2. Sin cámara o si el QR no lee: "No tengo cámara disponible -- buscar a mano" y busca por código, nombre o teléfono. Revisa permisos de cámara y brillo de la pantalla del cliente. No crees otra reserva para solucionar el escaneo.
3. Botones manuales: "Marcar check-in" (Por llegar), "Falta check-in — marcar ahora" (tarjeta) o "Marcar check-in — ofrecer consumo →" (ficha).

Pantalla "Check-in":
- "Registro de acompañantes — obligatorio": nombre y apellido de cada persona extra (RUT opcional). Sin eso no deja avanzar ("Faltan N acompañante(s) por registrar…").
- "Ofrecer productos y combos": ofrece y carga solo lo que el cliente acepta.
- "Registrar check-in" (en ingreso presencial: "Confirmar ingreso"). Si no cargó nada aparece "🎁 El cliente llega sin nada cargado" con upsells: "Agregar lo elegido y registrar check-in" o "El cliente no quiere nada — check-in igual".
- **La habitación tiene que estar pagada antes del check-in.** Si falta pagar, el sistema te lleva primero a "Registrar pago" con el aviso "La habitación tiene que estar pagada antes del check-in. Falta cobrar $X." Después del pago sigue el check-in. Solo los consumos y upsells que se agreguen al entrar pueden quedar como "Falta cobrar" en la tarjeta y se cobran durante la estadía o antes de finalizar.

**Atrasos**: tolerancia por defecto de 15 minutos (la define el administrador en Atrasos). Dentro de la tolerancia el cliente tiene su tiempo completo; pasada la tolerancia se descuenta del tiempo lo que exceda (o todo el atraso, según la regla configurada). La salida nunca pasa del cierre ni de la siguiente reserva. La pantalla lo avisa antes de confirmar: "Llega N min tarde — … sale a las HH:MM". Informa al cliente la hora de salida.

Bloqueos:
- "La habitación X está en estado aseo/mantencion — hay que reactivarla antes…" → primero marca aseo listo.
- "La habitación X todavía tiene un huésped adentro (sin check-out)…" → finaliza la reserva anterior.
- "No se puede adelantar el check-in…" → hay otra reserva o margen de aseo antes.
- "El horario de esta reserva ya terminó…" → usa "Modificar" para reprogramarla y luego haz el check-in.

## 6. Consumo, extras y hora adicional
- En la ficha, sección "Consumo / extras" (o "+ Agregar consumo →" en la tarjeta). Solo con check-in hecho.
- "Agregar al consumo": productos y combos con −/+ y el botón "Agregar N ítems · $X". Si algo no tiene stock, no se agrega nada de ese envío ("<Producto>: N, pediste M.").
- "+ Otro concepto que no está en la lista": descripción y monto (máximo $200.000).
- Después de agregar, si queda saldo, te lleva a "Registrar pago".
- **Hora adicional**: botón "⏱ Vender 1 hora adicional $X". Suma 1 hora a la salida y la carga como consumo. No se puede si pasa del cierre ("…pasa la hora de cierre") o si choca con la siguiente reserva ("…existe otra reserva después"). Si dice "Hora adicional sin precio", avisa al administrador. Una estadía corta no admite tiempo extra.
- El stock lo repone el administrador.

## 7. Pagos ("Registrar pago")
1. "¿Cómo paga?": elige el medio (efectivo, transferencia, débito, crédito u otro).
2. "¿Cuánto paga?": escribe el monto realmente recibido. Atajo "Saldo completo · $X".
3. Comprobante obligatorio:
   - Efectivo o transferencia: "N° de boleta".
   - Débito o crédito: "N° de voucher".
   - Otro: al menos uno de los dos.
4. "Registrar pago". Opcional: "+ Agregar una nota". Desde el tablero también aparece "Saltar por ahora →". Si vienes a hacer un check-in no hay forma de saltar el cobro: sin la habitación pagada no hay check-in ("Volver a la reserva" si el cliente no puede pagar).
- Registra solo pagos comprobados (plata recibida o voucher aprobado).
- No se puede cobrar más que el saldo: "El monto ($X) es mayor que el saldo pendiente ($Y)…". Si aparece, revisa si alguien ya registró un pago.
- Si la pantalla demora, revisa "Pagos registrados" antes de repetir: el sistema evita duplicados del mismo formulario.
- Mercado Pago no se registra a mano: se cobra con el link (sección 3) y se registra solo.
- Devoluciones: solo el administrador ("Devolver este pago").

## 8. Finalizar, aseo e inspecciones
**Finalizar (check-out)**: "Finalizar / Check-out →" en la tarjeta o "Finalizar reserva →" en la ficha.
1. Pregunta si hay consumos pendientes y agrégalos.
2. El saldo debe estar en $0 ("…todavía tiene un saldo pendiente — registra el pago completo…").
3. Revisa la habitación y marca "Confirmo que revisé la habitación, sus instalaciones, artículos y estado general, y está todo OK."
4. "Confirmar y finalizar reserva". La habitación pasa sola a "En aseo".

**Aseo**:
1. La mucama escanea el QR de la puerta, entra con su PIN y aprieta "Reportar aseo listo →".
2. En el tablero aparece "✓ <mucama> reportó aseo listo a las HH:MM — falta tu confirmación". El PIN ya identifica quién hizo el aseo.
3. Revisa la habitación y aprieta "Confirmar aseo de <mucama> — reactivar". No hay que elegir a nadie. Es la única forma de que vuelva a Disponibles.
- Si ninguna mucama lo reportó con su PIN (por ejemplo, no escaneó el QR), la tarjeta pide elegir "¿Quién hizo el aseo?" y luego "Marcar aseo listo — reactivar".
- Si no hay mucamas para elegir: "No hay mucamas activas cargadas en Personal" → avisa al administrador.
- "Aseo del día" (Operaciones) muestra salidas, aseos cerrados y pendientes. "Dónde están las mucamas" muestra en qué habitación está cada una.

**Ronda de turno** ("/ronda-de-turno"): recorre cada Playroom disponible. "📷 Escanear QR de la habitación →" y luego "Hacer inspección completa →" (hay que haber escaneado el QR en los últimos 10 minutos). Si está ocupada, queda pendiente.

**Inspección**: anfitrión, turno, fecha; OK o Falla en Toallas, Estufa, Espejos, Flogger, Confort / ropa de cama, Jabón / shampoo, Luces, Olor, Ceniceros, Pilas de chapa, Reponer artículos de aseo; mobiliario, "Desperfectos encontrados", observaciones y hasta 6 fotos. "Guardar inspección". Cualquier falla marca la habitación con "Mantención".

**Fuera de servicio**: en la tarjeta, "Cambiar estado" → "En mantención" o "Inactiva" y "Guardar". Para volver: "Activa" (si estaba en aseo, primero "Marcar aseo listo").

## 9. Caja
- "Caja": "Saldo en caja" (efectivo que debería haber ahora), cobros del día por medio de pago, de dónde viene la plata y "Movimientos del día". Navega con "← Día anterior", "Ir a hoy", "Día siguiente →".
- Registrar movimiento de caja chica: "Tipo" ("+ Ingreso (caja chica)" o "− Egreso"), "Monto (CLP)", "Descripción" y "Registrar". No se pueden editar ni borrar: revisa antes de guardar.
- No hay apertura ni cierre formal de caja. Al terminar el turno: cuenta el efectivo y compáralo con "Saldo en caja", registra retiros como "− Egreso".

## 10. Entrega de turno
Entrega al siguiente anfitrión: próximas llegadas ("Por llegar"), "Pendientes de gestionar", tarjetas con "Falta cobrar", habitaciones "En aseo" sin confirmar o en mantención, el efectivo contado y cualquier incidente con el código de la reserva. Cierra sesión con "Salir".

Para escalar un problema al administrador: "Reserva [código] / hora [hora] / intenté [acción] / apareció [mensaje] / verifiqué [resultado]".

## 11. Clientes
- "Clientes": buscar por nombre o teléfono; segmentos "Nuevos", "Alta recurrencia", "Baja recurrencia", "Esporádicos".
- Ficha del cliente: historial, "Editar datos del cliente" (el teléfono no se edita: es su identidad) y "Lista negra" (Sin restricción, Advertencia, Bloqueado, con motivo). Un cliente "Bloqueado" no puede reservar. Cambiar la lista negra es una decisión del administrador.
- El historial orienta la atención; no da descuentos automáticos.

## 12. Claves de acceso
- "Claves" (si tu perfil la tiene habilitada): claves de chapas de habitaciones y recintos. Están ocultas: "Ver" en una fila o "Mostrar todas"; se ocultan solas a los 2 minutos. Cada vez que alguien las muestra queda registrado.
- Solo el administrador puede agregarlas o cambiarlas. El asistente no conoce las claves.

## 13. Qué puede y qué no puede hacer el anfitrión
- Puede: crear reservas e ingresos, buscar, escanear, check-in, cobrar, consumo, hora adicional, finalizar, cancelar antes del check-in, mantener retenciones, corregir voucher o boleta, cambiar estado de habitaciones y marcar aseo listo, registrar movimientos de caja.
- Solo administrador: devoluciones, tarifas, ofertas, cupones, upsells, estadía corta, atrasos, habitaciones, personal y mucamas, productos y stock, claves, eliminar clientes.
- Si una pantalla dice "Tu perfil no tiene acceso a esta sección.", pide al administrador que la habilite.

## 14. Problemas frecuentes
| Situación | Qué hacer |
|---|---|
| No encuentro la reserva | Busca por teléfono, nombre y código. Confirma fecha y habitación con el cliente. |
| No deja hacer el check-in | Revisa estado de la reserva, que la habitación esté activa (aseo listo) y los acompañantes completos. |
| Rechaza un pago | Revisa monto, saldo y voucher/boleta. Si el pago ya aparece en "Pagos registrados", no lo registres otra vez. |
| Producto sin stock | No repitas el cargo; revisa qué quedó agregado y ofrece otra opción. |
| El tablero no cambia | "actualizar ahora". Se actualiza cada 30 segundos. |
| No aparece "Finalizar" | Falta el check-in o hay saldo pendiente. |
| El sistema se cae o da error | Anota hora, código y mensaje, y avisa al administrador. No crees reservas duplicadas. |
