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
    $commands=[
    "READ_FILE",
    "EXIT",
    "HELP"
];
    if (!in_array(trim($command),$commands)){
        echo "This command does not exist.\n";
        return true;
    }
    if (trim($command) === "EXIT"){
        return false;
    }
    if (trim($command) === "HELP"){
        echo "\nYou are a normal client you can use these commands:\n";
        echo "1. READ_FILE: This reads a file in the servers system. \n\n";
        return true;
    }

    $write_result = @socket_write($socket, $command, strlen($command));
    if ($write_result === false) {
        echo "Failed to send command, the server might have disconnected.\n";
        return false;  // Exit the loop if writing failed
    }
    echo "Sent command: $command\n";

    // Get the server's response
    $response = @socket_read($socket, 1024);

    if ($response === false || $response === '') {
        echo "Server has disconnected.\n";
        return false;
    }

    echo "Server response: $response\n";

    sleep(1); 
    return true;
}
$ison=true;
while($ison){
echo $response;
// Sample commands to send to the server
    echo "Enter Command: ";
    $ison=send_command($client_socket,readline()."\r\n");


}

socket_close($client_socket);
echo "Connection closed.\n";

?>
