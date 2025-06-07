<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Libraries\ChatWebSocketServer as ChatServer;
use React\EventLoop\Factory;
use React\Socket\SocketServer;

/**
 * Chat WebSocket Server Command
 * 
 * Starts the WebSocket server for the chat application
 */
class ChatWebSocketServer extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Chat';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'chat:websocket';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Starts the WebSocket server for the chat application';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'chat:websocket [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--port' => 'Port to run the WebSocket server on (default: 8080)',
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     * @return void
     */
    public function run(array $params)
    {
        $port = $params['port'] ?? CLI::getOption('port') ?? 8080;
        
        CLI::write('Starting Chat WebSocket Server on port ' . $port, 'green');
        
        $loop = Factory::create();
        $socket = new SocketServer('0.0.0.0:' . $port, [], $loop);
        
        $server = new IoServer(
            new HttpServer(
                new WsServer(
                    new ChatServer()
                )
            ),
            $socket,
            $loop
        );
        
        CLI::write('WebSocket Server running. Press Ctrl+C to stop.', 'yellow');
        
        $server->run();
    }
}