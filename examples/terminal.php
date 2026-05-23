<?php
/**
 * Example: open a terminal session against a QEMU VM.
 *
 * Run with:
 *
 *   composer require textalk/websocket
 *
 *   PBS_HOST=https://pbs.example.com:8007 \
 *   PBS_TOKEN='PBSAPIToken=root@pam!auto=...' \
 *   PBS_NODE=orca PBS_VMID=100 \
 *   php examples/terminal.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\Pbs\Configuration;
use ClientApi\Pbs\Pve;
use ClientApi\Pbs\TerminalTarget;

$host = getenv('PBS_HOST') ?: 'https://localhost:8007';
$config = (new Configuration())
    ->setHost($host . '/api2/json')
    // Proxmox Backup Server wants `Authorization: PBSAPIToken=<id>=<secret>` with NO
    // space; the openapi-generator's prefix-join would inject one,
    // so put the full prefixed string in the api_key value.
    ->setApiKey('Authorization', getenv('PBS_TOKEN') ?: '');

$node = getenv('PBS_NODE') ?: 'pbs1';
$vmid = (int) (getenv('PBS_VMID') ?: '100');

printf("Opening terminal on %s:qemu/%d...\n", $node, $vmid);

$target = new TerminalTarget(
    kind: TerminalTarget::KIND_QEMU,
    node: $node,
    vmid: $vmid,
);

$session = (new Pbs($config))->connectTerminal($target);
$session->resize(120, 32);
$session->send("uname -a\n");

$deadline = microtime(true) + 5.0;
while (microtime(true) < $deadline) {
    $msg = $session->recv();
    if ($msg === null) break;
    echo $msg;
}
$session->close();
