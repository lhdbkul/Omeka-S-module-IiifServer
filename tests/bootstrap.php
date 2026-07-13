<?php declare(strict_types=1);

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    // ?DigitalObject: registered globally via STI on resource, its table must
    // exist for resource value queries (linked resources).
    ['Common', 'IiifServer', '?DigitalObject'],
    'IiifServerTest',
    __DIR__ . '/IiifServerTest'
);
