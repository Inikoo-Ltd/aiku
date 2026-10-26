<?php

[$_, $in, $outFont, $outMap] = $argv;
$f = file_get_contents($in);
$u16 = fn (int $o) => unpack('n', $f, $o)[1];
$u32 = fn (int $o) => unpack('N', $f, $o)[1];
$tables = [];
for ($i = 0, $n = $u16(4); $i < $n; $i++) {
    $r = unpack('a4tag/Nsum/Noff/Nlen', $f, 12 + 16 * $i);
    $tables[$r['tag']] = ['off' => $r['off'], 'len' => $r['len']];
}
unset($tables['GDEF']);

$cmap = $tables['cmap']['off'];
$charToGlyph = [];
for ($i = 0, $n = $u16($cmap + 2); $i < $n; $i++) {
    $sub = $cmap + $u32($cmap + 4 + 8 * $i + 4);
    if ($u16($sub) === 12) {
        for ($g = 0, $groups = $u32($sub + 12); $g < $groups; $g++) {
            [$start, $end, $glyph] = [$u32($sub + 16 + 12 * $g), $u32($sub + 20 + 12 * $g), $u32($sub + 24 + 12 * $g)];
            for ($c = $start; $c <= $end; $c++) {
                $charToGlyph[$c] = $glyph + $c - $start;
            }
        }
        break;
    }
}
$glyphToChar = [];
foreach ($charToGlyph as $c => $g) {
    $glyphToChar[$g] ??= $c;
}

$coverage = function (int $o) use ($u16): array {
    $glyphs = [];
    if ($u16($o) === 1) {
        for ($i = 0, $n = $u16($o + 2); $i < $n; $i++) {
            $glyphs[] = $u16($o + 4 + 2 * $i);
        }
    } else {
        for ($i = 0, $n = $u16($o + 2); $i < $n; $i++) {
            for ($g = $u16($o + 4 + 6 * $i); $g <= $u16($o + 6 + 6 * $i); $g++) {
                $glyphs[] = $g;
            }
        }
    }
    return $glyphs;
};

$gsub = $tables['GSUB']['off'];
$lookupList = $gsub + $u16($gsub + 8);
$ligatures = [];
for ($l = 0, $n = $u16($lookupList); $l < $n; $l++) {
    $lookup = $lookupList + $u16($lookupList + 2 + 2 * $l);
    $type = $u16($lookup);
    for ($s = 0, $subs = $u16($lookup + 4); $s < $subs; $s++) {
        $sub = $lookup + $u16($lookup + 6 + 2 * $s);
        $subType = $type;
        if ($type === 7) {
            $subType = $u16($sub + 2);
            $sub += $u32($sub + 4);
        }
        if ($subType !== 4) {
            continue;
        }
        $firsts = $coverage($sub + $u16($sub + 2));
        for ($i = 0, $sets = $u16($sub + 4); $i < $sets; $i++) {
            $set = $sub + $u16($sub + 6 + 2 * $i);
            for ($j = 0, $count = $u16($set); $j < $count; $j++) {
                $lig = $set + $u16($set + 2 + 2 * $j);
                $components = [$firsts[$i]];
                for ($k = 1, $cc = $u16($lig + 2); $k < $cc; $k++) {
                    $components[] = $u16($lig + 2 + 2 * $k);
                }
                $ligatures[] = [$u16($lig), $components];
            }
        }
    }
}

$map = [];
$pua = 0xE000;
foreach ($ligatures as [$glyph, $components]) {
    $chars = array_map(fn ($g) => $glyphToChar[$g] ?? null, $components);
    if (in_array(null, $chars, true)) {
        continue;
    }
    $sequence = implode('', array_map('mb_chr', $chars));
    if (isset($map[$sequence])) {
        continue;
    }
    if (!isset($glyphToChar[$glyph]) || $glyphToChar[$glyph] >= 0x30000) {
        while (isset($charToGlyph[$pua])) {
            $pua++;
        }
        if ($pua > 0xF8FF) {
            exit("PUA full\n");
        }
        $charToGlyph[$pua] = $glyph;
        $glyphToChar[$glyph] = $pua;
        $map[$sequence] = mb_chr($pua++);
    } else {
        $map[$sequence] = mb_chr($glyphToChar[$glyph]);
    }
}

ksort($charToGlyph);
$groups = [];
foreach ($charToGlyph as $c => $g) {
    $last = count($groups) - 1;
    if ($last >= 0 && $groups[$last][1] === $c - 1 && $groups[$last][2] + ($c - $groups[$last][0]) === $g) {
        $groups[$last][1] = $c;
    } else {
        $groups[] = [$c, $c, $g];
    }
}
$format12 = pack('nnNNN', 12, 0, 16 + 12 * count($groups), 0, count($groups));
foreach ($groups as [$start, $end, $glyph]) {
    $format12 .= pack('NNN', $start, $end, $glyph);
}
$newCmap = pack('nnnnN', 0, 1, 3, 10, 12).$format12;

$data = [];
foreach ($tables as $tag => $t) {
    $data[$tag] = $tag === 'cmap' ? $newCmap : substr($f, $t['off'], $t['len']);
}
ksort($data, SORT_STRING);
$checksum = function (string $d): int {
    $d .= str_repeat("\0", (4 - strlen($d) % 4) % 4);
    $sum = 0;
    foreach (unpack('N*', $d) as $v) {
        $sum = ($sum + $v) & 0xFFFFFFFF;
    }
    return $sum;
};
$n = count($data);
$es = (int) floor(log($n, 2));
$sr = (2 ** $es) * 16;
$out = pack('Nnnnn', $u32(0), $n, $sr, $es, $n * 16 - $sr);
$offset = 12 + 16 * $n;
$body = '';
$headAt = 0;
foreach ($data as $tag => $d) {
    if ($tag === 'head') {
        $d = substr_replace($d, "\0\0\0\0", 8, 4);
        $headAt = $offset;
    }
    $out .= pack('a4NNN', $tag, $checksum($d), $offset, strlen($d));
    $d .= str_repeat("\0", (4 - strlen($d) % 4) % 4);
    $body .= $d;
    $offset += strlen($d);
}
$out .= $body;
$out = substr_replace($out, pack('N', (0xB1B0AFBA - $checksum($out)) & 0xFFFFFFFF), $headAt + 8, 4);
file_put_contents($outFont, $out);

uksort($map, fn ($a, $b) => strcmp($a, $b));
file_put_contents($outMap, "<?php\n\nreturn ".var_export($map, true).";\n");
echo count($ligatures)." ligatures, ".count($map)." sequences, PUA used ".($pua - 0xE000)."\n";
