<?php

declare(strict_types=1);

use function Flow\ETL\DSL\ref;
use Aeon\Calendar\Stopwatch;
use Flow\ETL\DSL\CSV;
use Flow\ETL\DSL\To;
use Flow\ETL\Flow;

require __DIR__ . '/../../../bootstrap.php';

$flow = (new Flow())
    ->read(CSV::from(__FLOW_DATA__ . '/partitioned'))
    ->collect()
    ->sortBy(ref('id'))
    ->write(To::output());

if ($_ENV['FLOW_PHAR_APP'] ?? false) {
    return $flow;
}

print "Reading partitioned CSV dataset...\n";

$stopwatch = new Stopwatch();
$stopwatch->start();

$flow->run();

$stopwatch->stop();

print "Total elapsed time: {$stopwatch->totalElapsedTime()->inSecondsPrecise()}s\n";
