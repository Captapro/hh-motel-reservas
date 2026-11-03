{{-- Píxel de Meta (el mismo de hhmotel.cl): mide visitas del catálogo y, con
     MetaConversions en el servidor, las ventas. Solo en el catálogo público. --}}
@if (config('services.meta.pixel_id'))
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', @json((string) config('services.meta.pixel_id')));
fbq('track', 'PageView');
</script>
@endif
