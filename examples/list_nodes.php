<?php
/**
 * Example: list cluster nodes.
 *
 * Run with:
 *
 *   PBS_HOST=https://pbs.example.com:8007 \
 *   PBS_TOKEN='PBSAPIToken=root@pam!auto:...' \
 *   php examples/list_nodes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\Pbs\Configuration;
use ClientApi\Pbs\Pve;

$host = getenv('PBS_HOST') ?: 'https://localhost:8007';
$config = (new Configuration())
    ->setHost($host . '/api2/json')
    ->setApiKey('Authorization', getenv('PBS_TOKEN') ?: '');

$pve = new Pbs($config);
$response = $pbs->nodes()->nodesGetNodes();
$nodes = $response->getData() ?? [];

printf("Found %d node(s):\n", count($nodes));
foreach ($nodes as $node) {
    printf(
        "  - %s (status=%s, cpu=%s, mem=%s/%s)\n",
        $node->getNode() ?? '?',
        $node->getStatus() ?? '?',
        $node->getCpu() ?? '?',
        $node->getMem() ?? '?',
        $node->getMaxmem() ?? '?',
    );
}
