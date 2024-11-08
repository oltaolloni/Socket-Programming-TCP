<?php

$server_ip = "127.0.0.1";
$server_port = 12345;
$client_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

if ($client_socket === false) {
    die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Connecting to server...\n";
$connection = socket_connect($client_socket, $server_ip, $server_port);

if ($connection === false) {
    die("Unable to connect to server: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Connected to server at $server_ip:$server_port\n";

// Function to send a command to the server
function send_command($socket, $command) {
    socket_write($socket, $command, strlen($command));
    echo "Sent command: $command\n";

    // Get the server's response
    $response = socket_read($socket, 1024);
    echo "Server response: $response\n";
}

// Sample commands to send to the server
$commands = [
    "READ_FILE\r\n",    // To read the content of server_file.txt
    "WRITE_FILE\r\n",   // To write to server_file.txt
    "UNKNOWN_CMD\r\n",  // Invalid command
];

foreach ($commands as $command) {
    send_command($client_socket, $command);
    sleep(1);  // Adding delay between commands
}

// Close the client socket after communication
socket_close($client_socket);
echo "Connection closed.\n";

?>
