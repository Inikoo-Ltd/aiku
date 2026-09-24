{{--
* Author Louis Perez
* Created on 23-09-2026-11h-34m
* GitHub: https://github.com/louis-perez
* Copyright 2026
--}}

{{--
    A4 sheet of unit labels, laid out by absolute position so every cell lands on the die cut of the
    stock rather than wherever the flow of the page happens to push it.
--}}

@php
    $mm = fn ($value) => number_format($value, 2, '.', '').'mm';
@endphp

@foreach ($cells as $cell)
    <div style="position: absolute; left: {{ $mm($cell['left']) }}; top: {{ $mm($cell['top']) }}; width: {{ $mm($cellWidth) }}; height: {{ $mm($cellHeight) }}; margin: 0; padding: {{ $mm($cellTopPadding) }} {{ $mm($cellPadding) }} {{ $mm($cellPadding) }}; overflow: hidden;@if ($cutGuides) border: 0.1mm dashed #b0b0b0;@endif">
        @include('labels.templates.pdf.org_stock.'.$level.'_body')
    </div>
@endforeach
