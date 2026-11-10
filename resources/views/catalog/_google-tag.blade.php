{{-- Etiqueta de Google (gtag.js): mide visitas del catálogo para GA4 / Google Ads y,
     en la página de reserva confirmada, la conversión. Solo en el catálogo público
     y solo si GOOGLE_TAG_ID está configurado. --}}
@if (config('services.google.tag_id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode((string) config('services.google.tag_id')) }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', @json((string) config('services.google.tag_id')));
</script>
@endif
