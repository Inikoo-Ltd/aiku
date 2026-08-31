@php
    $regions = [
        ['label' => 'Shop & Catalogue', 'category' => 'shop', 'color' => '#e88a73', 'size' => 'big', 'pos' => ['lg' => [185, 100], 'md' => [165, 100], 'sm' => [160, 95]]],
        ['label' => 'CRM & Chat', 'category' => 'crm', 'color' => '#b58ac2', 'size' => 'small', 'pos' => ['lg' => [490, 85], 'md' => [470, 100], 'sm' => [460, 95]]],
        ['label' => 'Orders', 'category' => 'orders', 'color' => '#f3c04a', 'size' => 'small', 'pos' => ['lg' => [780, 100], 'md' => [775, 100], 'sm' => [160, 245]]],
        ['label' => 'Procurement & Partners', 'category' => 'procurement', 'color' => '#9dcab0', 'size' => 'big', 'pos' => ['lg' => [465, 265], 'md' => [470, 265], 'sm' => [460, 245]]],
        ['label' => 'Warehouse', 'category' => 'warehouse', 'color' => '#76b7c2', 'size' => 'small', 'pos' => ['lg' => [775, 265], 'md' => [775, 265], 'sm' => [160, 395]]],
        ['label' => 'Dispatch', 'category' => 'dispatch', 'color' => '#7f9fd1', 'size' => 'small', 'pos' => ['lg' => [1045, 265], 'md' => [165, 430], 'sm' => [460, 395]]],
        ['label' => 'Production', 'category' => 'production', 'color' => '#c2a878', 'size' => 'small', 'pos' => ['lg' => [160, 265], 'md' => [165, 265], 'sm' => [160, 545]]],
        ['label' => 'Accounting', 'category' => 'accounting', 'color' => '#d1a3b5', 'size' => 'small', 'pos' => ['lg' => [160, 425], 'md' => [470, 430], 'sm' => [460, 545]]],
        ['label' => 'HR', 'category' => 'hr', 'color' => '#a3c17f', 'size' => 'small', 'pos' => ['lg' => [455, 430], 'md' => [775, 430], 'sm' => [160, 695]]],
        ['label' => 'My Profile', 'category' => 'profile', 'color' => '#8fc1b5', 'size' => 'small', 'pos' => ['lg' => [750, 430], 'md' => [165, 595], 'sm' => [460, 695]]],
        ['label' => 'Marketing', 'category' => 'marketing', 'color' => '#e0b04a', 'size' => 'small', 'pos' => ['lg' => [1045, 425], 'md' => [470, 595], 'sm' => [310, 845]]],
    ];
    $layouts = [
        'lg' => ['viewBox' => '0 0 1200 505', 'maxWidth' => '940px'],
        'md' => ['viewBox' => '0 0 940 675', 'maxWidth' => '660px'],
        'sm' => ['viewBox' => '0 0 620 940', 'maxWidth' => '420px'],
    ];
    $blobs = [
        'big' => 'M-145 -58 C-70 -84 80 -82 142 -52 C166 -12 163 42 132 66 C45 90 -68 88 -134 60 C-160 22 -160 -26 -145 -58 Z',
        'small' => 'M-112 -48 C-54 -68 60 -66 108 -42 C126 -10 124 34 100 52 C34 70 -52 68 -102 46 C-122 16 -122 -22 -112 -48 Z',
    ];
    $allDocs = \App\Actions\UI\AikuPublic\BlogPosts::all('docs');
    $docCount = fn (array $region) => $allDocs->where('category', $region['category'])->count();
@endphp
@if ($tag || $category)
    <style>
        .docsmap-mini { display: flex; flex-wrap: wrap; gap: 14px; margin: 18px 0 6px; font-family: 'Caveat','Segoe Print','Bradley Hand','Comic Sans MS',cursive; }
        .docsmap-mini a { position: relative; display: inline-block; padding: 3px 14px 5px; font-size: 21px; line-height: 1.2; color: #23222e; text-decoration: none; isolation: isolate; }
        .docsmap-mini a::before { content: ''; position: absolute; inset: -6px -10px; z-index: -1; transform: rotate(-0.7deg); background-color: color-mix(in srgb, var(--paint) 38%, transparent); -webkit-mask-image: var(--brush); mask-image: var(--brush); -webkit-mask-size: 100% 100%; mask-size: 100% 100%; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; }
        .docsmap-mini a:hover::before, .docsmap-mini a[aria-current]::before { background-color: color-mix(in srgb, var(--paint) 66%, transparent); }
        .docsmap-mini a span { opacity: .55; font-size: 17px; }
        .docsmap-mini .all::before { content: none; }
        .docsmap-mini .all { opacity: .65; font-size: 19px; }
    </style>
    <nav class="docsmap-mini" aria-label="Map of aiku modules"
        style="--brush:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 440 38' preserveAspectRatio='none'%3E%3Cpath d='M4%2018%20C48%207%20120%205%20196%208%20C268%2011%20352%204%20428%209%20C436%2013%20438%2024%20430%2029%20C356%2036%20268%2031%20178%2033%20C116%2034%2056%2034%2012%2028%20C4%2026%201%2022%204%2018%20Z' fill='%23000'/%3E%3C/svg%3E&quot;)">
        @foreach ($regions as $region)
            @php $count = $docCount($region); @endphp
            @php $isCurrent = $category === $region['category']; @endphp
            @if ($count > 0)
                <a href="{{ route('aiku-public.docs.index', ['category' => $region['category']]) }}"
                    style="--paint:{{ $region['color'] }}"
                    @if ($isCurrent) aria-current="true" @endif
                >{{ $region['label'] }} <span>{{ $count }}</span></a>
            @endif
        @endforeach
        <a class="all" href="{{ route('aiku-public.docs.index') }}">← {{ __('whole map') }}</a>
    </nav>
@else
<figure class="docsmap" aria-label="Map of aiku modules">
    <style>
        .docsmap { margin: 8px 0 0; }
        .docsmap svg { display: none; width: 100%; height: auto; margin: 0 auto; font-family: 'Caveat','Segoe Print','Bradley Hand','Comic Sans MS',cursive; }
        .docsmap .map-sm { display: block; }
        @media (min-width: 640px) { .docsmap .map-sm { display: none; } .docsmap .map-md { display: block; } }
        @media (min-width: 1000px) { .docsmap .map-md { display: none; } .docsmap .map-lg { display: block; } }
        .docsmap .pen { fill: none; stroke: #23222e; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; filter: url(#map-pen); }
        .docsmap .soft { stroke-width: 1.6; opacity: .55; }
        .docsmap .w { mix-blend-mode: multiply; filter: url(#map-bleed); stroke: none; }
        .docsmap .lbl { fill: #23222e; filter: url(#map-pen); font-size: 30px; text-anchor: middle; }
        .docsmap .lbl-long { font-size: 25px; }
        .docsmap .sub { fill: #23222e; opacity: .65; font-size: 19px; text-anchor: middle; }
        .docsmap .dormant { opacity: .38; }
        .docsmap a:hover .w, .docsmap a:focus .w { opacity: 1; }
        .docsmap a:hover .lbl, .docsmap a:focus .lbl { text-decoration: underline; }
        .docsmap a { outline: none; }
    </style>
    @foreach ($layouts as $layoutKey => $layout)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="{{ $layout['viewBox'] }}" role="img" class="map-{{ $layoutKey }}" style="max-width:{{ $layout['maxWidth'] }}">
            @if ($loop->first)
                <defs>
                    <filter id="map-pen" x="-10%" y="-10%" width="120%" height="120%">
                        <feTurbulence type="fractalNoise" baseFrequency="0.02" numOctaves="3" seed="5" result="n"/>
                        <feDisplacementMap in="SourceGraphic" in2="n" scale="3.5" xChannelSelector="R" yChannelSelector="G"/>
                    </filter>
                    <filter id="map-bleed" x="-30%" y="-30%" width="160%" height="160%">
                        <feTurbulence type="fractalNoise" baseFrequency="0.008 0.014" numOctaves="3" seed="9" result="n"/>
                        <feDisplacementMap in="SourceGraphic" in2="n" scale="18" xChannelSelector="R" yChannelSelector="G" result="d"/>
                        <feGaussianBlur in="d" stdDeviation="1.6" result="b"/>
                        <feTurbulence type="fractalNoise" baseFrequency="0.05" numOctaves="3" seed="4" result="g"/>
                        <feColorMatrix in="g" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 0.9 -0.2" result="ga"/>
                        <feComposite in="b" in2="ga" operator="in" result="mottled"/>
                        <feMerge><feMergeNode in="b"/><feMergeNode in="mottled"/></feMerge>
                    </filter>
                </defs>
            @endif

            @if ($layoutKey === 'lg')
                <g class="pen soft" stroke-dasharray="6 10">
                    <path d="M300 125 C440 155 620 155 700 120"/>
                    <path d="M790 155 C798 180 788 195 782 205"/>
                    <path d="M590 265 C630 263 650 265 668 265"/>
                    <path d="M262 265 C300 263 330 265 352 265"/>
                    <path d="M885 265 C920 263 940 265 958 265"/>
                    <path d="M700 115 l14 8 l-10 10 M774 197 l8 12 l12 -6 M660 257 l14 8 l-10 10 M344 257 l14 8 l-10 10 M950 257 l14 8 l-10 10"/>
                </g>
            @endif

            @foreach ($regions as $region)
                @php $count = $docCount($region); @endphp
                                @if ($count > 0)
                    <a href="{{ route('aiku-public.docs.index', ['category' => $region['category']]) }}" aria-label="{{ $region['label'] }} — {{ $count }} {{ Str::plural('guide', $count) }}">
                @endif
                <g transform="translate({{ $region['pos'][$layoutKey][0] }} {{ $region['pos'][$layoutKey][1] }})" @class(['dormant' => $count === 0])>
                    <path class="w" d="{{ $blobs[$region['size']] }}" fill="{{ $region['color'] }}" opacity="{{ $count > 0 ? '.34' : '.18' }}"/>
                    <path class="pen" d="{{ $blobs[$region['size']] }}"/>
                    <text @class(['lbl', 'lbl-long' => mb_strlen($region['label']) > 16]) y="-2">{{ $region['label'] }}</text>
                    <text class="sub" y="30">{{ $count > 0 ? $count.' '.Str::plural('guide', $count).' →' : 'soon' }}</text>
                </g>
                @if ($count > 0)
                    </a>
                @endif
            @endforeach
        </svg>
    @endforeach
    <figcaption style="text-align:center;color:var(--muted);font-size:14px;margin-top:4px">The map of aiku — the painted islands already have guides; click one to read them.</figcaption>
</figure>
@endif
