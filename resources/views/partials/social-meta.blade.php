@php
    $shareTitle = $shareTitle ?? 'HH Motel — Playrooms Santiago';
    $shareDescription = $shareDescription ?? 'Reserva tu playroom en HH Motel Santiago: elige tu Playroom, horario y duración, y confirma en línea.';
    $shareImage = $shareImage ?? asset('images/og-hh-motel.png');
@endphp
<meta name="description" content="{{ $shareDescription }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="HH Motel">
<meta property="og:locale" content="es_CL">
<meta property="og:title" content="{{ $shareTitle }}">
<meta property="og:description" content="{{ $shareDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $shareImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="HH Motel Santiago Playrooms">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $shareTitle }}">
<meta name="twitter:description" content="{{ $shareDescription }}">
<meta name="twitter:image" content="{{ $shareImage }}">
