<?php

declare(strict_types=1);

/** Split SQL into statements without splitting quoted or commented semicolons. */
function migration_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';
        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= "\n";
            }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote === null && $char === '-' && $next === '-' && (($sql[$i + 2] ?? '') === ' ' || ($sql[$i + 2] ?? '') === "\t")) {
            $lineComment = true;
            $i++;
            continue;
        }
        if ($quote === null && $char === '#') {
            $lineComment = true;
            continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $blockComment = true;
            $i++;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $quote !== '`' && $i + 1 < $length) $buffer .= $sql[++$i];
            elseif ($char === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) $buffer .= $sql[++$i];
                else $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
        } elseif ($char === ';') {
            if (trim($buffer) !== '') $statements[] = trim($buffer);
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }
    if ($quote !== null || $blockComment) throw new RuntimeException('SQL berisi string atau komentar yang tidak ditutup.');
    if (trim($buffer) !== '') $statements[] = trim($buffer);
    return $statements;
}

/** Keep the original alphabetical order unless a migration dependency requires waiting. */
function order_migrations(array $files, array $dependencies): array
{
    usort($files, static fn(string $left, string $right): int => strcmp(basename($left), basename($right)));
    $available = array_fill_keys(array_map('basename', $files), true);
    foreach ($dependencies as $migration => $required) {
        if (!isset($available[$migration])) throw new RuntimeException("Berkas migrasi tidak ditemukan: {$migration}");
        foreach ($required as $dependency) {
            if (!isset($available[$dependency])) throw new RuntimeException("Dependensi migrasi tidak ditemukan: {$dependency}");
        }
    }

    $pending = $files;
    $ordered = [];
    $orderedNames = [];
    while ($pending) {
        $progress = false;
        foreach ($pending as $index => $file) {
            $name = basename($file);
            if (array_diff($dependencies[$name] ?? [], $orderedNames)) continue;
            $ordered[] = $file;
            $orderedNames[] = $name;
            unset($pending[$index]);
            $progress = true;
            break;
        }
        if (!$progress) throw new RuntimeException('Dependensi migrasi membentuk siklus.');
    }
    return $ordered;
}
