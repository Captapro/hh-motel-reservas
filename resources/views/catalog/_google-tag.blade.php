{{-- Etiquetas de Google (gtag.js) del catálogo público: Google Ads (AW-…, conversiones) y
     Google Analytics 4 (G-…, visitas y recorrido). Una sola carga de gtag.js; cada etiqueta se
     activa solo si su ID está configurado. Los dominios del sitio de la marca y del catálogo se
     enlazan para que cuenten como un solo recorrido. --}}
@php
    $adsId = (string) config('services.google.tag_id');
    $gaId = (string) config('services.google.analytics_id');
    $firstId = $gaId !== '' ? $gaId : $adsId;
@endphp
@if ($firstId !== '')
<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($firstId) }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
@if ($adsId !== '')
gtag('config', @json($adsId));
@endif
@if ($gaId !== '')
gtag('config', @json($gaId), { linker: { domains: @json(config('services.google.linker_domains')) } });
@endif
</script>
@endif
