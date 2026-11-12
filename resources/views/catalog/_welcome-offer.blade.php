{{--
    Bienvenida para quien entra por primera vez al catálogo desde este teléfono
    o computador: si a los ~20 segundos todavía no reservó, se le ofrece el
    cupón de primera visita (HH Secret Pass). Quien reserva rápido paga precio
    normal. Se muestra solo durante la primera visita (3 horas desde que entró),
    una vez cerrado no vuelve, y tampoco aparece en quien ya reservó desde este
    equipo. El servidor igual rechaza el cupón si el teléfono ya tiene reservas
    (CouponValidator), así que borrar el navegador no sirve para repetirlo.

    $welcomeApplyInPage: en /catalogo/reservar, aplica el cupón con el campo
    "¿Tienes un cupón?" para no perder lo que el cliente ya eligió.
--}}
@php
    $welcomeCoupon = \App\Models\Coupon::where('origin', \App\Services\Pricing\CouponValidator::FIRST_VISIT_ORIGIN)
        ->where('is_active', true)->where('discount_type', 'percentage')->orderBy('id')->first();
    $welcomeApplyInPage = $welcomeApplyInPage ?? false;
@endphp
@if ($welcomeCoupon && ! request()->filled('cupon'))
    <style>
        .hh-welcome { position:fixed; inset:auto 0 0 0; z-index:80; display:flex; justify-content:center; padding:0 12px calc(12px + env(safe-area-inset-bottom)); pointer-events:none; }
        .hh-welcome[hidden] { display:none; }
        .hh-welcome-card { pointer-events:auto; width:100%; max-width:440px; background:#151618; border:1px solid #5a3f22; border-radius:16px; padding:18px 18px 16px; box-shadow:0 -10px 40px rgba(0,0,0,.55); color:#f1ece6; animation:hhWelcomeUp .35s ease-out; position:relative; }
        @keyframes hhWelcomeUp { from { transform:translateY(30px); opacity:0; } to { transform:none; opacity:1; } }
        .hh-welcome-kicker { color:#ffb27a; font-size:13px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .hh-welcome-title { font-size:21px; font-weight:800; margin:6px 0 4px; line-height:1.25; padding-right:26px; }
        .hh-welcome-text { color:#b9b4ae; font-size:15px; line-height:1.4; }
        .hh-welcome-btn { display:block; width:100%; margin-top:14px; min-height:48px; border:0; border-radius:10px; background:var(--hh-accent, #e8893c); color:#21170e; font-size:16.5px; font-weight:800; cursor:pointer; text-align:center; line-height:48px; text-decoration:none; }
        .hh-welcome-close { position:absolute; top:10px; right:10px; width:34px; height:34px; border:0; background:transparent; color:#8d8882; font-size:22px; cursor:pointer; }
        .hh-welcome-fine { color:#6f6b66; font-size:12.5px; margin-top:9px; text-align:center; }
        body.has-tabbar .hh-welcome { bottom:64px; }
        body.has-ctabar .hh-welcome { bottom:72px; }
    </style>
    <div class="hh-welcome" id="hh-welcome" hidden role="dialog" aria-label="Descuento de bienvenida">
        <div class="hh-welcome-card">
            <button type="button" class="hh-welcome-close" id="hh-welcome-close" aria-label="Cerrar">×</button>
            <div class="hh-welcome-kicker">✦ HH Secret Pass</div>
            <div class="hh-welcome-title">¿Primera vez en HH? Tienes {{ (int) $welcomeCoupon->discount_value }}% de bienvenida</div>
            <div class="hh-welcome-text">Válido en tu primera reserva, en cualquier Playroom. Reserva en línea en 1 minuto.</div>
            @if ($welcomeApplyInPage)
                <button type="button" class="hh-welcome-btn" id="hh-welcome-use">Usar mi {{ (int) $welcomeCoupon->discount_value }}%</button>
            @else
                <a class="hh-welcome-btn" id="hh-welcome-use" href="{{ route('catalog.reserve', array_filter(['cupon' => $welcomeCoupon->code, 'categoria' => $welcomeCategory ?? null])) }}">Usar mi {{ (int) $welcomeCoupon->discount_value }}%</a>
            @endif
            <div class="hh-welcome-fine">Solo para quienes nos visitan por primera vez.</div>
        </div>
    </div>
    <script>
    (function () {
        const KEY_FIRST = 'hh_primera_visita', KEY_DONE = 'hh_bienvenida_lista';
        const store = {
            get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
            set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        };
        if (store.get(KEY_DONE)) return;
        let first = Number(store.get(KEY_FIRST));
        if (!first) { first = Date.now(); store.set(KEY_FIRST, String(first)); }
        if (Date.now() - first > 3 * 3600 * 1000) return; // ya no es su primera visita

        const box = document.getElementById('hh-welcome');
        let shown = false;
        function show() {
            if (shown || store.get(KEY_DONE)) return;
            shown = true;
            box.hidden = false;
            if (window.gtag) gtag('event', 'hh_bienvenida_vista');
        }
        function done() { store.set(KEY_DONE, '1'); box.hidden = true; }
        setTimeout(show, 20000);
        document.getElementById('hh-welcome-close').addEventListener('click', done);
        document.getElementById('hh-welcome-use').addEventListener('click', function (e) {
            done();
            if (window.gtag) gtag('event', 'hh_bienvenida_usada');
            @if ($welcomeApplyInPage)
                const input = document.getElementById('coupon-input');
                const apply = document.getElementById('coupon-apply');
                if (input && apply) { input.value = {{ Js::from($welcomeCoupon->code) }}; apply.click(); }
            @endif
        });
    })();
    </script>
@endif
