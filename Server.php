<?php

$server_ip = "127.0.0.1";
$server_port = 12345;
$max_clients = 4;
$client_sockets = [];
$log_file = "server_logs.txt";
$messages_file = 'client_messages.txt';

// Krijimi i socket-it të serverit
$server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$server_socket) {
    die("Nuk u krijua socket-i: " . socket_strerror(socket_last_error()) . "\n");
}

socket_bind($server_socket, $server_ip, $server_port) or die("Nuk u arrit lidhja me IP dhe portin\n");
socket_listen($server_socket) or die("Serveri nuk mund të dëgjojë për lidhjet\n");

echo "Serveri po dëgjon në IP: $server_ip dhe portin: $server_port\n";

// Funksion për log-imin e kërkesave
function log_request($message) {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

while (true) {
    // Kontrollon për lidhje të reja dhe pranimin e tyre
    $read_sockets = $client_sockets;
    $read_sockets[] = $server_socket;
    $write = null;
    $except = null;

    if (socket_select($read_sockets, $write, $except, 0) < 1) {
        continue;
    }

    if (in_array($server_socket, $read_sockets)) {
        $new_socket = socket_accept($server_socket);
        if ($new_socket && count($client_sockets) < $max_clients) {
            $client_sockets[] = $new_socket;
            $client_ip = '';
            socket_getpeername($new_socket, $client_ip);
            echo "Lidhje e re nga klienti me IP: $client_ip\n";
            log_request("Lidhje e re nga klienti me IP: $client_ip");
        } else {
            // Refuzon lidhjen nëse numri i klientëve është maksimal
            socket_close($new_socket);
            echo "Serveri është i plotë, lidhja u refuzua\n";
        }
        unset($read_sockets[array_search($server_socket, $read_sockets)]);
    }

}
socket_close($server_socket);

?>