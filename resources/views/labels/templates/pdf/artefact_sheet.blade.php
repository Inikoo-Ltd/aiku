{{--
* Author: Vika Aqordi <aqordeon@gmail.com>
* Created: Tue, 09 Sep 2026, Bali, Indonesia
* Copyright (c) 2026, Inikoo LTD
--}}

@php
    $mm = fn ($value) => number_format($value, 2, '.', '').'mm';
@endphp

@foreach ($cells as $cell)
    <div style="position: absolute; left: {{ $mm($cell['left']) }}; top: {{ $mm($cell['top']) }}; width: {{ $mm($labelWidth) }}; height: {{ $mm($labelHeight) }}; margin: 0; padding: 0; overflow: hidden; @if ($cutGuides) border: 0.1mm dashed #b0b0b0; @endif">
        @if ($imageSource)
            <img src="{{ $imageSource }}" width="{{ $mm($labelWidth) }}" height="{{ $mm($labelHeight) }}" @if ($imageRotation) rotate="{{ $imageRotation }}" @endif />
        @endif
    </div>

    @foreach ($fields as $field)
        @if ($field['barcode'])
            <div style="position: absolute; left: {{ $mm($cell['left'] + $field['left']) }}; top: {{ $mm($cell['top'] + $field['top']) }}; width: {{ $mm($field['width']) }}; height: {{ $mm($field['height']) }}; margin: 0; padding: 0;@if ($field['background_color']) background-color: {{ $field['background_color'] }};@endif @if ($field['rotation']) rotate: {{ $field['rotation'] }};@endif"><img src="{{ $field['barcode']['uri'] }}" width="{{ $mm($field['barcode']['width']) }}" height="{{ $mm($field['barcode']['height']) }}" />@if ($field['barcode']['show_value'])<div style="width: {{ $mm($field['barcode']['width']) }}; margin: 0; padding: 0; text-align: center; font-family: Arial, sans-serif; font-size: {{ $field['font_size'] }}pt; font-weight: {{ $field['weight'] }}; line-height: 1.1; color: {{ $field['color'] }};">{{ $field['text'] }}</div>@endif</div>
        @elseif ($field['icons'])
            <div style="position: absolute; left: {{ $mm($cell['left'] + $field['left']) }}; top: {{ $mm($cell['top'] + $field['top']) }}; width: {{ $mm($field['width']) }}; height: {{ $mm($field['height']) }}; margin: 0; padding: 0; line-height: 1;@if ($field['rotation']) rotate: {{ $field['rotation'] }};@endif">@foreach ($field['icons']['sources'] as $iconSource)<img src="{{ $iconSource }}" width="{{ $mm($field['icons']['size']) }}" height="{{ $mm($field['icons']['size']) }}" @if (!$loop->last) style="margin-right: {{ $mm($field['icons']['gap']) }};" @endif />@endforeach</div>
        @else
            <div style="position: absolute; left: {{ $mm($cell['left'] + $field['left']) }}; top: {{ $mm($cell['top'] + $field['top']) }}; width: {{ $mm($field['width']) }}; height: {{ $mm($field['height']) }}; margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: {{ $field['font_size'] }}pt; font-weight: {{ $field['weight'] }}; line-height: 1.1; color: {{ $field['color'] }};@if ($field['rotation']) rotate: {{ $field['rotation'] }};@endif">@if ($field['background_color'])<span style="background-color: {{ $field['background_color'] }};">{!! nl2br(e($field['text'])) !!}</span>@else{!! nl2br(e($field['text'])) !!}@endif</div>
        @endif
    @endforeach
@endforeach
