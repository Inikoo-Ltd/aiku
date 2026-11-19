@php
    $columns = max(array_map(fn ($row) => count($row), $rows ?: [[]]));
    $headings = ['Delivery note', 'Product references', 'Shipper/Exporter'];
@endphp
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 8pt; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 4px; vertical-align: top; }
        tr.heading td { font-weight: bold; border-bottom: 1px solid #555; background: #f1f1f1; }
        tr.total td { font-weight: bold; border-top: 1px solid #555; }
        tr.title td { font-size: 13pt; font-weight: bold; padding-bottom: 6px; }
        tr.section td { font-size: 10pt; font-weight: bold; padding-top: 8px; }
        tr.void td { font-size: 12pt; font-weight: bold; color: #b00; }
        tr.note td { font-size: 7pt; padding-top: 8px; }
        td.number { text-align: right; }
    </style>
</head>
<body>
<table>
    @foreach ($rows as $index => $row)
        @php
            $first = (string) ($row[0] ?? '');
            $class = match (true) {
                $row === [] => 'blank',
                str_starts_with($first, 'VOID ') => 'void',
                in_array($first, ['INVOICE', 'CONSOLIDATED DELIVERY NOTE'], true) => 'title',
                in_array($first, $headings, true) && count($row) > 2 => 'heading',
                $first === 'TOTAL' => 'total',
                count($row) === 1 && strlen($first) > 80 => 'note',
                count($row) === 1 && strtoupper($first) === $first => 'section',
                default => '',
            };
        @endphp
        @if ($row === [])
            <tr><td colspan="{{ $columns }}">&nbsp;</td></tr>
        @elseif (count($row) === 1)
            <tr class="{{ $class }}"><td colspan="{{ $columns }}">{{ $first }}</td></tr>
        @elseif (count($row) === 2 && $class === '')
            <tr><td><b>{{ $row[0] }}</b></td><td colspan="{{ $columns - 1 }}">{{ $row[1] }}</td></tr>
        @else
            <tr class="{{ $class }}">
                @foreach ($row as $cell)
                    <td class="{{ is_int($cell) || is_float($cell) ? 'number' : '' }}">{{ is_float($cell) ? rtrim(rtrim(number_format($cell, 3, '.', ','), '0'), '.') : $cell }}</td>
                @endforeach
                @for ($i = count($row); $i < $columns; $i++)
                    <td></td>
                @endfor
            </tr>
        @endif
    @endforeach
</table>
</body>
</html>
