<?php

use App\Http\Controllers\Admin\AccessController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CleanupController;
use App\Http\Controllers\Admin\ComboController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerBehaviorController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\FurnitureController;
use App\Http\Controllers\Admin\GhlTestController;
use App\Http\Controllers\Admin\LateArrivalSettingController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RateRuleController;
use App\Http\Controllers\Admin\RoomCategoryController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\ShortStaySettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UpsellController;
use App\Http\Controllers\AseoSummaryController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingAddonController;
use App\Http\Controllers\BookingCancelController;
use App\Http\Controllers\BookingCheckInController;
use App\Http\Controllers\BookingDeleteController;
use App\Http\Controllers\BookingFinalizeController;
use App\Http\Controllers\BookingHoldController;
use App\Http\Controllers\BookingPassController;
use App\Http\Controllers\BookingPaymentInstructionsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerLoyaltyController;
use App\Http\Controllers\DailyAseoController;
use App\Http\Controllers\DailySalesController;
use App\Http\Controllers\GuestReviewController;
use App\Http\Controllers\ImageResizeController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\AccessCodeController;
use App\Http\Controllers\MonthlySalesController;
use App\Http\Controllers\MucamaActivityController;
use App\Http\Controllers\MucamaAuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentDocumentController;
use App\Http\Controllers\PaymentRefundController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationSearchController;
use App\Http\Controllers\RoomBoardController;
use App\Http\Controllers\RoomInspectionController;
use App\Http\Controllers\RoomQrController;
use App\Http\Controllers\SaleDetailController;
use App\Http\Controllers\ShiftRoundController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('rooms.board');
});

// Público -- sin login, pensado para compartir el link con clientes.
Route::get('/img', [ImageResizeController::class, 'show'])->middleware('throttle:240,1,img.resize')->name('img.resize');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/habitacion/{room}', [CatalogController::class, 'room'])->name('catalog.room');
Route::get('/catalogo/reservar', [PublicBookingController::class, 'create'])->name('catalog.reserve');
Route::get('/catalogo/reservar/horarios', [PublicBookingController::class, 'times'])->middleware('throttle:30,1,catalog.reserve.times')->name('catalog.reserve.times');
Route::get('/catalogo/reservar/disponibilidad', [PublicBookingController::class, 'checkAvailability'])->middleware('throttle:30,1,catalog.reserve.availability')->name('catalog.reserve.availability');
Route::post('/catalogo/reservar', [PublicBookingController::class, 'store'])->middleware('throttle:8,1,catalog.reserve.store')->name('catalog.reserve.store');
// pass_token, no code -- el código es secuencial por día (fácil de
// adivinar) y esta página muestra habitación, fecha, consumo y saldo sin
// pedir sesión.
Route::get('/catalogo/pagar/{token}', [MercadoPagoController::class, 'pay'])->middleware('throttle:10,1,catalog.pay')->name('catalog.pay');
Route::post('/webhooks/mercadopago', [MercadoPagoController::class, 'webhook'])->middleware('throttle:120,1,webhooks.mercadopago')->name('webhooks.mercadopago');
Route::get('/catalogo/reservado/{token}', [PublicBookingController::class, 'booked'])->name('catalog.booked');
// El pase PDF se manda directo al cliente por WhatsApp (ver el botón de
// "instrucciones de pago" en reservations/show) -- tiene que poder abrirlo
// sin loguearse. Usa pass_token (aleatorio) y NO code (secuencial por día,
// fácil de adivinar probando números cercanos) como token de acceso.
Route::get('/reservas/pase/{token}', [BookingPassController::class, 'preview'])->name('bookings.pass.preview');
Route::get('/reservas/pase/{token}/pdf', [BookingPassController::class, 'download'])->name('bookings.pass.pdf');

// Opinión del huésped -- 4-5 estrellas va directo a Google, 0-3 se queda
// adentro (ver GuestReviewController). {code} es opcional: el QR fijo de
// recepción no viene de una reserva puntual, el link post-check-out sí.
Route::get('/opinion/{code?}', [GuestReviewController::class, 'create'])->name('reviews.create');
Route::post('/opinion/{code?}', [GuestReviewController::class, 'store'])->middleware('throttle:10,1,reviews.store')->name('reviews.store');
Route::get('/opinion/{review}/comentario', [GuestReviewController::class, 'editComment'])->name('reviews.comment.edit');
Route::post('/opinion/{review}/comentario', [GuestReviewController::class, 'updateComment'])->middleware('throttle:10,1,reviews.comment.update')->name('reviews.comment.update');

// QR pegado en la puerta de cada habitación + panel general de mucamas --
// login liviano por PIN (ver MucamaAuthController), no el guard de auth()
// del panel interno (Staff no es un usuario del sistema).
Route::get('/qr/mucamas/entrar', [MucamaAuthController::class, 'showLogin'])->name('mucamas.login');
Route::post('/qr/mucamas/entrar', [MucamaAuthController::class, 'login'])->middleware('throttle:15,1,mucamas.login.store')->name('mucamas.login.store');
Route::post('/qr/mucamas/salir', [MucamaAuthController::class, 'logout'])->name('mucamas.logout');

Route::get('/qr/habitacion/{room}', [RoomQrController::class, 'show'])->name('rooms.qr.show');
Route::get('/qr/habitacion/{room}/imagen.png', [RoomQrController::class, 'image'])->name('rooms.qr.image');
Route::post('/qr/habitacion/{room}/aseo', [RoomQrController::class, 'reportAseo'])->middleware(['throttle:20,1,rooms.qr.report_aseo', 'mucama'])->name('rooms.qr.report_aseo');
Route::post('/qr/habitacion/{room}/confirmar', [RoomQrController::class, 'confirmRoomReady'])->middleware(['throttle:20,1,rooms.qr.confirm_ready', 'mucama'])->name('rooms.qr.confirm_ready');
Route::get('/qr/mucamas', [RoomQrController::class, 'mucamaPanel'])->middleware('mucama')->name('rooms.qr.mucama_panel');
Route::get('/qr/mucamas/imagen.png', [RoomQrController::class, 'mucamaPanelImage'])->name('rooms.qr.mucama_panel_image');
Route::get('/qr/ronda-de-turno/imagen.png', [RoomQrController::class, 'shiftRoundImage'])->name('rooms.qr.shift_round_image');

Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware(['guest', 'throttle:8,1,login.store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'section'])->group(function () {

    Route::get('/reservar', [ReservationController::class, 'create'])->name('reservations.create');
    Route::get('/clientes/buscar', [ReservationController::class, 'lookupCustomer'])->middleware('throttle:40,1,customers.lookup')->name('customers.lookup');
    Route::get('/reservar/precio', [ReservationController::class, 'priceQuote'])->middleware('throttle:80,1,reservations.price')->name('reservations.price');
    Route::get('/buscar', [ReservationSearchController::class, 'index'])->name('reservations.search');
    Route::get('/calendario', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/reservar', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservas/escanear', [ReservationController::class, 'scan'])->name('reservations.scan');
    Route::get('/reservas/escanear/qr.png', [ReservationController::class, 'scanQrImage'])->name('reservations.scan_qr_image');
    Route::get('/reservas/{code}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::get('/reservas/{code}/editar', [ReservationController::class, 'edit'])->name('reservations.edit');
    Route::put('/reservas/{code}', [ReservationController::class, 'update'])->name('reservations.update');

    Route::get('/ventas/reserva/{code}', [SaleDetailController::class, 'show'])->name('sales.detail');
    Route::get('/ventas/{date?}', [DailySalesController::class, 'show'])->name('sales.daily');
    Route::post('/reservas/{code}/pagos/{payment}/devolver', [PaymentRefundController::class, 'store'])->middleware('role:administrador')->name('payments.refund');
    Route::put('/pagos/{payment}/documento', [PaymentDocumentController::class, 'update'])->name('payments.document');
    Route::get('/resumen-mensual/{month?}', [MonthlySalesController::class, 'show'])->name('sales.monthly');
    Route::get('/claves', [AccessCodeController::class, 'index'])->name('access_codes.index');
    Route::post('/claves/ver', [AccessCodeController::class, 'reveal'])->middleware('throttle:30,1,access_codes.reveal')->name('access_codes.reveal');
    Route::middleware('role:administrador')->group(function () {
        Route::put('/claves/habitacion/{room}', [AccessCodeController::class, 'saveRoom'])->name('access_codes.room');
        Route::post('/claves/importar', [AccessCodeController::class, 'import'])->name('access_codes.import');
        Route::post('/claves', [AccessCodeController::class, 'store'])->name('access_codes.store');
        Route::put('/claves/{accessCode}', [AccessCodeController::class, 'update'])->name('access_codes.update');
        Route::delete('/claves/{accessCode}', [AccessCodeController::class, 'destroy'])->name('access_codes.destroy');
    });
    Route::get('/caja/{date?}', [CashRegisterController::class, 'show'])->name('cash.show');
    Route::post('/caja', [CashRegisterController::class, 'store'])->name('cash.store');
    Route::get('/aseo/semana/{date?}', [AseoSummaryController::class, 'week'])->name('aseo.week');
    Route::get('/aseo/mes/{date?}', [AseoSummaryController::class, 'month'])->name('aseo.month');
    Route::get('/aseo/{date?}', [DailyAseoController::class, 'show'])->name('aseo.daily');

    Route::get('/habitaciones', [RoomBoardController::class, 'index'])->name('rooms.board');
    Route::get('/habitaciones/pases', [RoomBoardController::class, 'passScans'])->name('rooms.pass_scans');
    Route::get('/habitaciones/proximas', [RoomBoardController::class, 'upcoming'])->name('rooms.upcoming');
    Route::get('/habitaciones/{room}/reservas', [RoomBoardController::class, 'bookings'])->name('rooms.bookings');
    Route::post('/habitaciones/{room}/estado', [RoomBoardController::class, 'updateStatus'])->name('rooms.status');
    Route::post('/habitaciones/{room}/aseo-listo', [RoomBoardController::class, 'markAseoReady'])->name('rooms.aseo_ready');
    Route::get('/mucamas/actividad', [MucamaActivityController::class, 'index'])->name('mucamas.activity');
    Route::get('/mucamas/actividad/estado', [MucamaActivityController::class, 'status'])->name('mucamas.activity.status');
    Route::get('/ronda-de-turno', [ShiftRoundController::class, 'index'])->name('shift_round.index');
    Route::get('/ronda-de-turno/escanear', [ShiftRoundController::class, 'scan'])->name('shift_round.scan');
    Route::get('/mantencion', [RoomInspectionController::class, 'panel'])->name('rooms.inspections.panel');
    Route::get('/habitaciones/{room}/inspeccion', [RoomInspectionController::class, 'create'])->name('rooms.inspections.create');
    Route::post('/habitaciones/{room}/inspeccion', [RoomInspectionController::class, 'store'])->name('rooms.inspections.store');
    Route::post('/qr/habitacion/{room}/ocupada', [RoomQrController::class, 'acknowledgeOccupied'])->name('rooms.qr.acknowledge_occupied');
    Route::post('/qr/habitacion/{room}/confirmar-escaneo', [RoomQrController::class, 'confirmScan'])->name('rooms.qr.confirm_scan');

    Route::get('/reservas/{code}/pago', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/reservas/{code}/pago', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/reservas/{code}/consumo', [BookingAddonController::class, 'store'])->name('addons.store');
    Route::post('/reservas/{code}/hora-adicional', [BookingAddonController::class, 'extraHour'])->name('addons.extra-hour');
    // Destino del QR del pase: lleva a lo que falta (cobrar el saldo y el check-in) según el estado.
    Route::get('/reservas/{code}/validar', [BookingCheckInController::class, 'validatePass'])->name('bookings.validate');
    Route::get('/reservas/{code}/checkin', [BookingCheckInController::class, 'show'])->name('bookings.checkin.show');
    Route::post('/reservas/{code}/checkin', [BookingCheckInController::class, 'store'])->name('bookings.checkin');
    Route::post('/reservas/{code}/cancelar', [BookingCancelController::class, 'store'])->name('bookings.cancel');
    Route::post('/reservas/{code}/mantener', [BookingHoldController::class, 'store'])->name('bookings.hold');
    Route::delete('/reservas/{code}', [BookingDeleteController::class, 'destroy'])->middleware('role:soporte')->name('bookings.destroy');
    Route::post('/reservas/{code}/fidelizacion', [CustomerLoyaltyController::class, 'store'])->name('customers.loyalty');
    Route::post('/reservas/{code}/instrucciones-pago', [BookingPaymentInstructionsController::class, 'store'])->name('bookings.payment_instructions');
    Route::get('/reservas/{code}/finalizar', [BookingFinalizeController::class, 'show'])->name('bookings.finalize.show');
    Route::post('/reservas/{code}/finalizar', [BookingFinalizeController::class, 'store'])->name('bookings.finalize.store');

}); // fin del grupo auth

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    // Configuración -- solo administrador. Clientes/Productos/Combos/Analytics
    // quedan fuera de este bloque porque son herramientas de uso diario que
    // recepción también necesita (ver riel izquierdo del tablero).
    Route::middleware('role:administrador')->group(function () {
        Route::get('/mobiliario', [FurnitureController::class, 'index'])->name('furniture.index');
        Route::post('/mobiliario/categorias', [FurnitureController::class, 'category'])->name('furniture.categories.store');
        Route::put('/mobiliario/categorias/{category}', [FurnitureController::class, 'category'])->name('furniture.categories.update');
        Route::delete('/mobiliario/categorias/{category}', [FurnitureController::class, 'destroyCategory'])->name('furniture.categories.destroy');
        Route::post('/mobiliario/elementos', [FurnitureController::class, 'item'])->name('furniture.items.store');
        Route::put('/mobiliario/elementos/{item}', [FurnitureController::class, 'item'])->name('furniture.items.update');
        Route::delete('/mobiliario/elementos/{item}', [FurnitureController::class, 'destroyItem'])->name('furniture.items.destroy');
        Route::redirect('/', '/admin/categorias');

        Route::get('/categorias', [RoomCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categorias/crear', [RoomCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categorias', [RoomCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categorias/{category}/editar', [RoomCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categorias/{category}', [RoomCategoryController::class, 'update'])->name('categories.update');
        Route::post('/categorias/{category}/toggle', [RoomCategoryController::class, 'toggleCategory'])->name('categories.toggle');
        Route::post('/pisos/{field}/toggle', [RoomCategoryController::class, 'toggleFloor'])
            ->whereIn('field', ['piso_1_enabled', 'piso_2_norte_enabled', 'piso_2_sur_enabled', 'piso_3_norte_enabled', 'piso_3_sur_enabled'])
            ->name('floors.toggle');

        Route::get('/habitaciones', [AdminRoomController::class, 'index'])->name('rooms.index');
        Route::get('/habitaciones/crear', [AdminRoomController::class, 'create'])->name('rooms.create');
        Route::get('/habitaciones/qr', [AdminRoomController::class, 'qrSheet'])->name('rooms.qr_sheet');
        Route::post('/habitaciones', [AdminRoomController::class, 'store'])->name('rooms.store');
        Route::get('/habitaciones/{room}/editar', [AdminRoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/habitaciones/{room}', [AdminRoomController::class, 'update'])->name('rooms.update');

        Route::get('/tarifas', [RateRuleController::class, 'index'])->name('rates.index');
        Route::get('/tarifas/{rateRule}', [RateRuleController::class, 'edit'])->name('rates.edit');
        Route::put('/tarifas/{rateRule}', [RateRuleController::class, 'update'])->name('rates.update');
        Route::post('/tarifas/{rateRule}/precios', [RateRuleController::class, 'storePrice'])->name('rates.prices.store');
        Route::put('/tarifas/precios/{price}', [RateRuleController::class, 'updatePrice'])->name('rates.prices.update');
        Route::delete('/tarifas/precios/{price}', [RateRuleController::class, 'destroyPrice'])->name('rates.prices.destroy');

        Route::get('/ofertas', [OfferController::class, 'index'])->name('offers.index');
        Route::get('/ofertas/crear', [OfferController::class, 'create'])->name('offers.create');
        Route::post('/ofertas', [OfferController::class, 'store'])->name('offers.store');
        Route::get('/ofertas/{offer}/editar', [OfferController::class, 'edit'])->name('offers.edit');
        Route::put('/ofertas/{offer}', [OfferController::class, 'update'])->name('offers.update');
        Route::post('/ofertas/{offer}/toggle', [OfferController::class, 'toggle'])->name('offers.toggle');

        Route::get('/upsells', [UpsellController::class, 'index'])->name('upsells.index');
        Route::get('/upsells/crear', [UpsellController::class, 'create'])->name('upsells.create');
        Route::post('/upsells', [UpsellController::class, 'store'])->name('upsells.store');
        Route::get('/upsells/{upsell}/editar', [UpsellController::class, 'edit'])->name('upsells.edit');
        Route::put('/upsells/{upsell}', [UpsellController::class, 'update'])->name('upsells.update');
        Route::post('/upsells/{upsell}/toggle', [UpsellController::class, 'toggle'])->name('upsells.toggle');
        Route::delete('/upsells/{upsell}', [UpsellController::class, 'destroy'])->name('upsells.destroy');

        Route::get('/estadia-corta', [ShortStaySettingController::class, 'edit'])->name('short_stay.edit');
        Route::put('/estadia-corta', [ShortStaySettingController::class, 'update'])->name('short_stay.update');
        Route::get('/atrasos', [LateArrivalSettingController::class, 'edit'])->name('late_arrival.edit');
        Route::put('/atrasos', [LateArrivalSettingController::class, 'update'])->name('late_arrival.update');

        Route::get('/cupones', [CouponController::class, 'index'])->name('coupons.index');
        Route::get('/cupones/crear', [CouponController::class, 'create'])->name('coupons.create');
        Route::post('/cupones', [CouponController::class, 'store'])->name('coupons.store');
        Route::get('/cupones/{coupon}/editar', [CouponController::class, 'edit'])->name('coupons.edit');
        Route::put('/cupones/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    }); // fin role:administrador

    // Ver el stock queda para recepción (lo necesitan para saber qué ofrecer);
    // crear, editar y reponer inventario queda solo para administrador.
    Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
    Route::get('/combos', [ComboController::class, 'index'])->name('combos.index');

    Route::middleware('role:administrador')->group(function () {
        Route::get('/productos/crear', [ProductController::class, 'create'])->name('products.create');
        Route::post('/productos', [ProductController::class, 'store'])->name('products.store');
        Route::get('/productos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/productos/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/productos/{product}/stock', [ProductController::class, 'restock'])->name('products.restock');

        Route::get('/combos/crear', [ComboController::class, 'create'])->name('combos.create');
        Route::post('/combos', [ComboController::class, 'store'])->name('combos.store');
        Route::get('/combos/{combo}/editar', [ComboController::class, 'edit'])->name('combos.edit');
        Route::put('/combos/{combo}', [ComboController::class, 'update'])->name('combos.update');
        Route::delete('/combos/{combo}', [ComboController::class, 'destroy'])->name('combos.destroy');
        Route::post('/combos/{combo}/items', [ComboController::class, 'storeItem'])->name('combos.items.store');
        Route::delete('/combos/{combo}/items/{item}', [ComboController::class, 'destroyItem'])->name('combos.items.destroy');
    }); // fin role:administrador

    Route::post('/clientes/reglas', [AdminCustomerController::class, 'updateRules'])->middleware('role:administrador')->name('customers.rules.update');
    Route::post('/clientes/lista-negra/importar', [AdminCustomerController::class, 'importBlacklist'])->middleware('role:administrador')->name('customers.blacklist.import');
    Route::get('/clientes', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/clientes/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::put('/clientes/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::delete('/clientes/{customer}', [AdminCustomerController::class, 'destroy'])->middleware('role:administrador')->name('customers.destroy');
    Route::put('/clientes/{customer}/lista-negra', [AdminCustomerController::class, 'blacklist'])->name('customers.blacklist');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/exportar', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/analytics/clientes', [CustomerBehaviorController::class, 'index'])->name('analytics.customers');

    Route::get('/opiniones', [App\Http\Controllers\Admin\GuestReviewController::class, 'index'])->name('reviews.index');
    Route::delete('/opiniones', [App\Http\Controllers\Admin\GuestReviewController::class, 'destroyAll'])->middleware('role:administrador')->name('reviews.destroy_all');
    Route::get('/opiniones/qr.png', [App\Http\Controllers\Admin\GuestReviewController::class, 'qrImage'])->name('reviews.qr_image');

    Route::middleware('role:administrador')->group(function () {
        Route::get('/personal', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/personal', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/personal/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('/personal/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

        Route::get('/accesos', [AccessController::class, 'index'])->middleware('role:soporte')->name('access.index');
        Route::put('/accesos', [AccessController::class, 'update'])->middleware('role:soporte')->name('access.update');
        Route::post('/cuentas', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('/cuentas/{user}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/cuentas/{user}', [AccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/turnos', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('/turnos', [ShiftController::class, 'store'])->name('shifts.store');
        Route::put('/turnos/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
        Route::delete('/turnos/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');

        Route::get('/limpieza', [CleanupController::class, 'index'])->middleware('role:soporte')->name('cleanup.index');
        Route::delete('/limpieza', [CleanupController::class, 'destroy'])->middleware('role:soporte')->name('cleanup.destroy');

        Route::get('/integraciones/ghl', [GhlTestController::class, 'index'])->middleware('role:soporte')->name('ghl.index');
        Route::post('/integraciones/ghl/probar', [GhlTestController::class, 'test'])->middleware('role:soporte')->name('ghl.test');
    }); // fin role:administrador
});
