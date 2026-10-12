<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use MailMcp\Infrastructure\Config\MailConfig;
use MailMcp\Infrastructure\Imap\FolderResolver;
use MailMcp\Infrastructure\Imap\ImapConnectionFactory;
use MailMcp\Infrastructure\Imap\ImapMailboxReader;
use MailMcp\Ports\MailboxReader;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

/*
 * Session strategy: FileSessionStore (MCP_SESSION_DIR).
 *
 * The SDK can answer modern-era (SEP-2575) requests without a session, and
 * StreamableHttpTransport routes those automatically. Handshake-era clients
 * (including MCP Inspector) still open an initialize session and send follow-up
 * requests with Mcp-Session-Id. PHP handles each HTTP request in a separate
 * process here, so InMemorySessionStore cannot share that state. A dedicated
 * StatelessHttpTransport / buildStateless() path is modern-era-only and would
 * break Inspector. FileSessionStore under MCP_SESSION_DIR is the fit.
 */

$psr17Factory = new Psr17Factory();
$requestCreator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
);
$request = $requestCreator->fromGlobals();

if ($request->getUri()->getPath() !== '/mcp') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit(1);
}

$sessionDir = getenv('MCP_SESSION_DIR') ?: '/var/mcp-sessions';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
    throw new RuntimeException(sprintf('Unable to create MCP session directory: %s', $sessionDir));
}

$mailConfig = MailConfig::fromEnvironment();
$mailboxReader = new ImapMailboxReader(
    new ImapConnectionFactory($mailConfig),
    new FolderResolver($mailConfig),
);

$container = new Container();
$container->set(MailConfig::class, $mailConfig);
$container->set(MailboxReader::class, $mailboxReader);

$server = Server::builder()
    ->setServerInfo('mail-mcp', '0.1.0')
    ->setContainer($container)
    ->setDiscovery(dirname(__DIR__), ['src'], ['vendor', 'tests'])
    ->setSession(new FileSessionStore($sessionDir))
    ->build();

// DnsRebindingProtectionMiddleware ignores ports; allow the hostnames used in
// compose (mail-mcp) and local inspector (localhost / 127.0.0.1), including :8080.
$transport = new StreamableHttpTransport(
    $request,
    middleware: [
        new CorsMiddleware(),
        new DnsRebindingProtectionMiddleware(
            allowedHosts: ['mail-mcp', 'localhost', '127.0.0.1'],
        ),
    ],
);

emitResponse($server->run($transport));

function emitResponse(ResponseInterface $response): never
{
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    echo $response->getBody();
    exit(0);
}
