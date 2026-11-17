<?php

namespace App\Support;

use App\Models\AuditLog;

/** Nombres en español de lo que guarda la bitácora (AuditLog) y su resumen, para la pantalla «Actividad y accesos». */
class ActivityLabels
{
    /** Grupos para filtrar: nombre => prefijos de acción. */
    public const GROUPS = [
        'reservas' => ['Reservas y cupones', ['reserva.', 'cupon.']],
        'pagos' => ['Pagos y devoluciones', ['pago.']],
        'claves' => ['Claves de puertas', ['claves.']],
        'cuentas' => ['Cuentas, personal y accesos', ['cuenta.', 'personal.', 'accesos.', 'turno.', 'ronda.']],
        'habitaciones' => ['Habitaciones y aseo', ['habitacion.', 'operacion.']],
        'precios' => ['Precios, ofertas y extras', ['tarifa.', 'oferta.', 'upsell.', 'combo.', 'extra_core.', 'estadia_corta.', 'atrasos.']],
        'clientes' => ['Clientes y carnets', ['cliente.', 'carnet.']],
    ];

    /** Acciones que conviene mirar con lupa: se cuentan por persona. */
    public const SENSITIVE = ['claves.ver', 'claves.cambiar', 'claves.borrar', 'reserva.eliminar', 'reserva.corregir_venta', 'reserva.corregir_horas', 'pago.devolver', 'pago.devolver_parcial', 'pago.cobro_de_mas', 'reserva.consumo_quitado', 'cupon.quitado_sin_respaldo', 'cuenta.crear', 'cuenta.editar', 'cuenta.eliminar', 'accesos.editar', 'personal.eliminar'];

    private const LABELS = [
        'accesos.editar' => 'Cambió los accesos por perfil',
        'cuenta.crear' => 'Creó una cuenta', 'cuenta.editar' => 'Editó una cuenta', 'cuenta.eliminar' => 'Eliminó una cuenta', 'cuenta.cambiar_clave' => 'Cambió una contraseña',
        'personal.crear' => 'Agregó personal', 'personal.editar' => 'Editó personal', 'personal.eliminar' => 'Eliminó personal',
        'turno.crear' => 'Creó un turno', 'turno.editar' => 'Editó un turno', 'turno.eliminar' => 'Eliminó un turno',
        'ronda.pin_creado' => 'Creó un PIN de ronda', 'ronda.pin_borrado' => 'Borró un PIN de ronda',
        'claves.ver' => 'Vio una clave de puerta', 'claves.cambiar' => 'Cambió una clave', 'claves.crear' => 'Creó una clave', 'claves.borrar' => 'Borró una clave', 'claves.importar' => 'Importó claves',
        'reserva.crear' => 'Creó una reserva', 'reserva.crear_desde_planilla' => 'Cargó una reserva de la planilla', 'reserva.modificar' => 'Modificó una reserva', 'reserva.cancelar' => 'Canceló una reserva',
        'reserva.eliminar' => 'Eliminó una reserva', 'reserva.check_in' => 'Hizo check-in', 'reserva.check_in_sin_documento' => 'Check-in sin foto del documento del cupón', 'reserva.finalizar' => 'Finalizó una reserva', 'reserva.deshacer_check_in' => 'Deshizo un check-in', 'reserva.reactivar' => 'Reactivó una reserva',
        'reserva.expirar' => 'Venció una reserva', 'reserva.mantener_retencion' => 'Mantuvo una reserva retenida', 'reserva.instrucciones_pago_enviadas' => 'Envió instrucciones de pago',
        'reserva.corregir_horas' => 'Corrigió las horas reales', 'reserva.corregir_venta' => 'Corrigió el precio de una venta',
        'reserva.consumo_agregar' => 'Agregó un consumo', 'reserva.consumo_desde_planilla' => 'Cargó un consumo de planilla', 'reserva.consumo_entregado' => 'Entregó un consumo', 'reserva.consumo_quitado' => 'Quitó un consumo',
        'pago.registrar' => 'Registró un pago', 'pago.registrar_desde_planilla' => 'Cargó un pago de planilla', 'pago.mercadopago' => 'Pago de Mercado Pago', 'pago.documento' => 'Cargó un documento de pago',
        'pago.devolver' => 'Devolvió un pago', 'pago.devolver_parcial' => 'Devolvió parte de un pago', 'pago.cobro_de_mas' => 'Registró un cobro de más',
        'cupon.aplicado_despues' => 'Aplicó un cupón después', 'cupon.eliminar' => 'Eliminó un cupón', 'cupon.quitado_sin_respaldo' => 'Quitó un cupón sin respaldo', 'cupon.respaldo_foto' => 'Subió el respaldo de un cupón',
        'habitacion.aseo_listo' => 'Marcó aseo listo', 'habitacion.aseo_reportado' => 'Reportó aseo', 'habitacion.enviar_aseo' => 'Mandó una pieza a aseo', 'habitacion.estado_operativo' => 'Cambió el estado de una pieza',
        'habitacion.inspeccionar' => 'Inspeccionó una pieza', 'habitacion.qr_visita' => 'Visita por QR', 'habitacion.revision_previa' => 'Revisión previa de pieza', 'habitacion.ronda_ocupada' => 'Ronda con pieza ocupada',
        'operacion.categoria' => 'Cambió una categoría', 'operacion.piso' => 'Cambió un piso',
        'tarifa.editar' => 'Editó una tarifa', 'tarifa.precio_crear' => 'Creó un precio', 'tarifa.precio_editar' => 'Editó un precio', 'tarifa.precio_eliminar' => 'Eliminó un precio',
        'oferta.toggle' => 'Activó o pausó una oferta', 'upsell.toggle' => 'Activó o pausó un upsell', 'upsell.eliminar' => 'Eliminó un upsell',
        'combo.catalogo' => 'Cambió un combo del catálogo', 'combo.eliminar' => 'Eliminó un combo', 'combo.item_agregar' => 'Agregó un producto a un combo', 'combo.item_eliminar' => 'Quitó un producto de un combo', 'combo.toggle' => 'Activó o pausó un combo',
        'extra_core.crear' => 'Creó un extra', 'extra_core.modificar' => 'Modificó un extra', 'extra_core.quitar' => 'Quitó un extra',
        'cliente.datos_check_in' => 'Cargó datos del cliente al ingresar', 'cliente.eliminar' => 'Eliminó un cliente', 'cliente.telefono_corregir' => 'Corrigió el teléfono de un cliente',
        'cliente.rut_mercadopago' => 'Cargó un RUT de Mercado Pago', 'cliente.tarjeta_fidelizacion_entregar' => 'Entregó una tarjeta de fidelización', 'carnet.escaneo' => 'Escaneó un carnet',
        'conversacion.respondida' => 'Respondió una conversación', 'conversacion.ficha_editada' => 'Editó la ficha de una conversación',
        'novedad.editada' => 'Editó una novedad', 'novedad.eliminada' => 'Eliminó una novedad', 'aviso.enviar' => 'Envió un aviso',
        'producto.cuadre_turno' => 'Cuadró productos al turno', 'historico.cargar' => 'Cargó datos históricos', 'historico.quitar' => 'Quitó datos históricos', 'meta.ventas_pasadas' => 'Envió ventas pasadas a Meta',
        'analista.nota' => 'Anotó en el analista', 'analista.nota_borrar' => 'Borró una nota del analista', 'analista.contexto' => 'Cambió el contexto del analista', 'analista.borrar_conversacion' => 'Borró una conversación del analista', 'analista.borrar_todo' => 'Borró todo el analista',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? ucfirst(str_replace(['.', '_'], [' · ', ' '], $action));
    }

    /** Qué cambió, en pocas palabras. Nunca muestra claves, PIN ni tokens. */
    public static function summary(AuditLog $log): string
    {
        $old = (array) $log->old_value;
        $new = (array) $log->new_value;
        $parts = [];
        $hide = fn (string $key) => (bool) preg_match('/clave|password|pin|token|code|codigo|secret/i', $key);
        $show = function ($v) {
            if ($v === null || $v === '') {
                return '—';
            }
            if (is_bool($v)) {
                return $v ? 'sí' : 'no';
            }
            $text = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);

            return mb_strlen($text) > 40 ? mb_substr($text, 0, 40).'…' : $text;
        };

        foreach ($new as $key => $value) {
            if (count($parts) >= 3) {
                break;
            }
            if (is_int($key)) {
                continue;
            }
            if ($hide((string) $key)) {
                $parts[] = $key.': ••••';

                continue;
            }
            if (array_key_exists($key, $old) && $old[$key] != $value) {
                $parts[] = $key.': '.$show($old[$key]).' → '.$show($value);
            } elseif (! array_key_exists($key, $old) && ! is_array($value)) {
                $parts[] = $key.': '.$show($value);
            }
        }

        return implode(' · ', $parts);
    }
}
