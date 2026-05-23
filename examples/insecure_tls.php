<?php
/**
 * Example: connect to a Proxmox host with a self-signed certificate.
 *
 * The PVE web UI ships with a self-signed cert by default. Production
 * setups should use a real CA-signed cert (Let's Encrypt via the
 * Proxmox UI), but home-lab and dev setups commonly need to opt out
 * of cert verification.
 *
 * **Security note:** disabling verification is vulnerable to MITM.
 * Use only on trusted networks.
 *
 * Run with:
 *
 *   composer require textalk/websocket
 *
 *   PBS_HOST=https://pbs.example.com:8007 \
 *   PBS_TOKEN='PBSAPIToken=root@pam!auto=...' \
 *   PBS_NODE=orca PBS_VMID=100 \
 *   php examples/insecure_tls.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClientApi\Pbs\Configuration;
use ClientApi\Pbs\Pve;
use ClientApi\Pbs\TerminalTarget;
use ClientApi\Pbs\TextalkTransport;
use GuzzleHttp\Client as GuzzleClient;

$host = getenv('PBS_HOST') ?: 'https://localhost:8007';
$token = getenv('PBS_TOKEN') ?: '';

$config = (new Configuration())
    ->setHost($host . '/api2/json')
    // Full `PBSAPIToken=…` string goes in here; PHP's prefix-join
    // adds a space between the prefix and the value, but PVE rejects
    // `PBSAPIToken= <value>` (with space). Set the full string and
    // leave the prefix unset.
    ->setApiKey('Authorization', $token);

// ── 1. REST: a Guzzle client with `verify => false` for self-signed PVE.
// ── 2. WebSocket: TextalkTransport::insecure() builds a stream_context
//      with verify_peer=false / allow_self_signed=true.
//
// The `Pbs` facade carries both; per-tag accessors and connectTerminal
// honor the one you pass in.
$pve = new Pbs(
    config: $config,
    http: new GuzzleClient(['verify' => false, 'timeout' => 30]),
    wsTransport: TextalkTransport::insecure(),
);

$response = $pbs->nodes()->nodesGetNodes();
$nodes = $response->getData() ?? [];
printf("Connected (insecure TLS): %d node(s)\n", count($nodes));
foreach ($nodes as $n) {
    $arr = is_object($n) && method_exists($n, 'jsonSerialize') ? (array) $n->jsonSerialize() : (array) $n;
    printf("  - %s (status=%s)\n", $arr['node'] ?? '?', $arr['status'] ?? '?');
}

if (!getenv('PBS_NODE') || !getenv('PBS_VMID')) {
    echo "(skip terminal: set PBS_NODE and PBS_VMID to test the WebSocket leg)\n";
    exit(0);
}

$target = new TerminalTarget(
    kind: TerminalTarget::KIND_QEMU,
    node: getenv('PBS_NODE'),
    vmid: (int) getenv('PBS_VMID'),
);

$session = $pbs->connectTerminal($target);
$session->send("uname -a\n");

$deadline = microtime(true) + 3.0;
while (microtime(true) < $deadline) {
    $msg = $session->recv();
    if ($msg === null) break;
    echo $msg;
}
$session->close();
