<?php
declare(strict_types=1);

namespace Spires\Irc;

use Spires\Contracts\Core\Core;
use Spires\Core\Dispatcher;
use Spires\Irc\Message\Inbound\RawMessage;

class Client
{
    private $messages = [
        'connecting' => 'Spires connecting',
        'booted' => 'Spires booted',
        'listening' => 'Spires listening',
    ];

    /**
     * @var Core
     */
    private $core;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var User
     */
    private $user;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(Core $core, Connection $connection, User $user, Dispatcher $dispatcher)
    {
        $this->core = $core;
        $this->connection = $connection;
        $this->user = $user;
        $this->dispatcher = $dispatcher;
    }

    public function connection() : Connection
    {
        return $this->connection;
    }

    public function channel() : string
    {
        return $this->connection()->channel();
    }

    public function user() : User
    {
        return $this->user;
    }

    public function socket() : resource
    {
        return $this->socket;
    }

    public function connect()
    {
        $this->logHeading($this->messages['connecting']);

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_connect(
            $this->socket,
            $this->connection()->server(),
            $this->connection()->port()
        );

        $this->write("NICK {$this->user()->nickname()}");
        $this->write("USER {$this->user()->username()} {$this->user()->usermode()} * :{$this->user()->realname()}");
        $this->write("JOIN {$this->connection()->channel()}");
    }

    public function read()
    {
        return socket_read($this->socket, 2048, PHP_NORMAL_READ);
    }

    public function write(string $response)
    {
        $response = trim($response);

        $this->logWrite($response);
        return socket_write($this->socket, $response . "\r\n");
    }

    public function logCore(Core $core)
    {
        $this->logHeading($this->messages['booted']);

        $this->logDebug("Providers:");
        foreach ($core->getLoadedProviders() as $provider => $active) {
            $this->logDebug("  - " . $provider);
        }

        $this->logDebug("Plugins:");
        foreach ($core->getPlugins() as $name => $plugin) {
            $this->logDebug("  - " . $name);
        }
    }

    public function log(string $title, string $string)
    {
        $time = date('H:i:s');
        $title = str_pad($title, 8, ' ', STR_PAD_RIGHT);

        fwrite(STDOUT, "[{$time}|{$title}]: " . $string . "\n");
    }

    public function logNewLine()
    {
        fwrite(STDOUT, "\n");
    }

    public function logLine()
    {
        fwrite(STDOUT, str_repeat('_', 80) . "\n");
    }

    public function logHeading(string $title)
    {
        $title = str_repeat('=', 5) . ' ' . $title . ' ';
        $title = str_pad($title, 60, '=', STR_PAD_RIGHT);
        $line = str_repeat('=', 60);

        $this->logNewLine();
        $this->log('debug', $line);
        $this->log('debug', $title);
        $this->log('debug', $line);
        $this->logNewLine();
    }

    public function logDebug(string $string)
    {
        $this->log('debug', $string);
    }

    public function logRead(string $string)
    {
        $this->log('read', $string);
    }

    public function logWrite(string $string)
    {
        $this->log('write', $string);
    }

    public function run()
    {
        $this->logHeading($this->messages['booted']);

        $parser = new Parser();

        while ($raw = $this->read()) {
            if (!$raw = trim($raw)) {
                continue;
            }
            $this->logLine();
            $this->logRead($raw);

            $message = RawMessage::fromArray(
                $parser->parse($raw . "\r\n")
            );
            $this->dispatcher->dispatch($message);
        }
    }
}
