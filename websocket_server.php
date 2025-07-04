<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class StatusUpdateServer implements MessageComponentInterface {
    protected $clients;
    protected $statusCache = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->startPolling(); // Start polling the database for updates
    }

    private function startPolling() {
        $pollInterval = 2; // Poll every 2 seconds

        // Use a separate thread or loop to poll the database
        $loop = \React\EventLoop\Loop::get();
        $loop->addPeriodicTimer($pollInterval, function () {
            include 'db_connect.php';

            // Log the polling execution
            error_log("Polling database for status updates...");

            $sql = "SELECT id, status FROM login_attempts";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $attemptId = $row['id'];
                    $status = $row['status'];

                    // Log the current status for debugging
                    error_log("Attempt ID: {$attemptId}, Status: {$status}");

                    // Check if the status has changed
                    if (!isset($this->statusCache[$attemptId]) || $this->statusCache[$attemptId] !== $status) {
                        $this->statusCache[$attemptId] = $status;

                        // Notify all connected clients about the status change
                        foreach ($this->clients as $client) {
                            $client->send(json_encode(['attempt_id' => $attemptId, 'status' => $status]));
                        }
                    }
                }
            } else {
                // Log if no rows are returned
                error_log("No status updates found in the database.");
            }
        });

        $loop->run();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (isset($data['attempt_id'])) {
            $attemptId = $data['attempt_id'];

            // Check the database for the status of the attempt
            include 'db_connect.php';
            $sql = "SELECT status FROM login_attempts WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $attemptId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $status = $row['status'];

                // Notify the client about the status
                $from->send(json_encode(['status' => $status]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new StatusUpdateServer()
        )
    ),
    8080
);

$server->run();
?>