<?php

declare(strict_types=1);

// Execute the documentation's actual code with local geocoding fixtures.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/fixtures/psr-geocoding.php';

// Replace only transport arguments in provider constructors. The documented
// API calls, parsing, geometry and examples themselves execute unchanged.
$parser=(new PhpParser\ParserFactory())->createForNewestSupportedVersion();
$printer=new PhpParser\PrettyPrinter\Standard();
$traverser=new PhpParser\NodeTraverser(new class extends PhpParser\NodeVisitorAbstract {
    public function enterNode(PhpParser\Node $node): ?PhpParser\Node {
        if(!$node instanceof PhpParser\Node\Expr\New_ || !$node->class instanceof PhpParser\Node\Name) {return null;}
        $name=$node->class->getLast();
        if(!in_array($name,['GoogleGeocoder','NominatimGeocoder'],true)) {return null;}
        $offset=$name==='GoogleGeocoder'?1:2;
        foreach(['client'=>'GeoLocationTests\\DocumentationHttpClient','requestFactory'=>'Nyholm\\Psr7\\Factory\\Psr17Factory'] as $argument=>$class) {
            $replacement=new PhpParser\Node\Expr\New_(new PhpParser\Node\Name\FullyQualified($class));
            $found=false;
            foreach($node->args as $index=>$arg) {
                if(($arg->name!==null && $arg->name->toString()===$argument) || ($arg->name===null && $index===$offset)) {
                    $arg->value=$replacement;$found=true;break;
                }
            }
            if(!$found) {$node->args[]=new PhpParser\Node\Arg($replacement,name:new PhpParser\Node\Identifier($argument));}
            $offset++;
        }
        return $node;
    }
});

$examples = [];
$root = dirname(__DIR__);
foreach (array_merge([$root . '/README.md', $root . '/UPGRADE-3.0.md'], glob($root . '/docs/*.md')) as $document) {
    preg_match_all('/```php\s*\n(.*?)\n```/s', file_get_contents($document), $matches);
    foreach ($matches[1] as $index => $example) {
        if (!str_contains($example, 'vendor/autoload.php')) {
            throw new RuntimeException(basename($document) . ' example ' . ($index + 1) . ' has no autoloader.');
        }
        $examples[] = [basename($document) . ' example ' . ($index + 1), $example];
    }
}
if ($examples === []) { throw new RuntimeException('No PHP documentation examples found.'); }
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    foreach ($examples as [$label, $example]) {

        $example = str_replace("__DIR__ . '/vendor/autoload.php'",
            var_export(__DIR__ . '/../vendor/autoload.php', true), $example);
        $path = tempnam(sys_get_temp_dir(), 'geolocation-readme-');
        try {
            file_put_contents($path, $printer->prettyPrintFile($traverser->traverse($parser->parse($example))));
            ob_start();
            try {
                (static function (string $file): void { require $file; })($path);
            } catch (Throwable $error) {
                throw new RuntimeException($label . ' failed.', 0, $error);
            } finally {
                ob_end_clean();
            }
        } finally {
            unlink($path);
        }
    }
} finally {
    restore_error_handler();
}
printf("%d documentation examples passed on PHP %s.\n", count($examples), PHP_VERSION);
