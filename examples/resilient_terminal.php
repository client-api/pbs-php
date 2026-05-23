<?php
/**
 * Example: resilient terminal session with auto-reconnect.
 *
 * Run with:
 *
 *   composer require textalk/websocket
 *
 *   PBS_HOST=https://pbs.example.com:8007 \
 *   PBS_TOKEN='PBSAPIToken=root@pam!auto=...' \
 *   PBS_NODE=orca PBS_VMID=100 \
 *   php examples/resilient_terminal.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\Pbs\Configuration;
use ClientApi\Pbs\ResilientTerminalSession;
use ClientApi\Pbs\RetryOptions;
use ClientApi\Pbs\TerminalTarget;

$host = getenv('PBS_HOST') ?: 'https://localhost:8007';
$config = (new Configuration())
    ->setHost($host . '/api2/json')
    ->setApiKey('Authorization', getenv('PBS_TOKEN') ?: '');

$node = getenv('PBS_NODE') ?: 'pbs1';
$vmid = (int) (getenv('PBS_VMID') ?: '100');

$target = new TerminalTarget(
    kind: TerminalTarget::KIND_QEMU,
    node: $node,
    vmid: $vmid,
);

$session = new ResilientTerminalSession(
    config: $config,
    target: $target,
    retry: new RetryOptions(maxRetries: 20, initialDelaySeconds: 0.25),
);

$session->send("date\n");

$deadline = microtime(true) + 5 * 60;
$nextCmd = microtime(true) + 30;
while (microtime(true) < $deadline) {
    $msg = $session->recv();
    if ($msg === null) break;
    echo $msg;
    if (microtime(true) >= $nextCmd) {
        $session->send("date\n");
        $nextCmd = microtime(true) + 30;
    }
}
$session->close();
