<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Deptrac checks class dependencies. This AST check also rejects I/O functions,
// optional extensions, dynamic calls, includes and ambient state in the Core.
$allowed = array_fill_keys(explode(' ',
    'abs acos acosh asin asinh atan atan2 atanh ceil cos cosh deg2rad exp expm1 floor fmod hypot log log10 log1p max min pi pow rad2deg round sin sinh sqrt tan tanh ' .
    'is_array is_bool is_float is_int is_numeric is_object is_string is_finite is_infinite is_nan is_null ' .
    'array_key_exists array_keys array_values array_map array_filter array_reduce array_slice array_merge array_reverse array_unique array_search array_sum count in_array range sort usort ' .
    'strtolower strtoupper strlen substr str_contains str_starts_with str_ends_with strcmp trim sprintf implode explode get_debug_type'
), true);
$parser = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
$finder = new PhpParser\NodeFinder();
$failures = [];
$root = dirname(__DIR__) . '/src/AnthonyMartin/GeoLocation/';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
    if ($file->getExtension() !== 'php') { continue; }
    $relative = substr($file->getPathname(), strlen($root));
    if (preg_match('~^(Geodesy|Format|Sql|Index|Geocoding|Laravel)/~', $relative)) { continue; }
    if ($relative === 'GoogleGeocoder.php') { continue; } // CDZI-075: remove after moving adapter.
    $nodes = $parser->parse(file_get_contents($file->getPathname()));
    foreach ($finder->find($nodes, static fn($n) => $n instanceof PhpParser\Node\Expr) as $node) {
        $reason = null;
        if ($node instanceof PhpParser\Node\Expr\FuncCall) {
            if (!$node->name instanceof PhpParser\Node\Name || !isset($allowed[strtolower($node->name->toString())])) {
                $reason = 'Core function must be on the reviewed pure-PHP allowlist';
            }
        } elseif ($node instanceof PhpParser\Node\Expr\Include_ || $node instanceof PhpParser\Node\Expr\Eval_ || $node instanceof PhpParser\Node\Expr\ShellExec || $node instanceof PhpParser\Node\Expr\Exit_) {
            $reason = 'Core cannot perform I/O or evaluate external code';
        } elseif ($node instanceof PhpParser\Node\Expr\Variable && (!is_string($node->name) || in_array($node->name, ['GLOBALS', '_SERVER', '_GET', '_POST', '_COOKIE', '_FILES', '_ENV', '_REQUEST', '_SESSION'], true))) {
            $reason = 'Core cannot read ambient or dynamic variables';
        }
        if ($reason !== null) { $failures[] = "$relative:{$node->getStartLine()}: $reason"; }
    }
}
if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Core purity check passed.\n";
