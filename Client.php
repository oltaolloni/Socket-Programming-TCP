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
    

    $write_result = socket_write($socket, $command, strlen($command));
    if ($write_result === false) {
        echo "Failed to send command, the server might have disconnected.\n";
        return false;  // Exit the loop if writing failed
    }
    $length=1049;
    echo "Sent command: $command\n";
    // Get the server's response
    $response = socket_read($socket,$length);

    if ($response === false || $response === '') {
        echo "Server has disconnected.\n";
        return false;
    }

    
    echo "Server response: $response\n";
    if (trim($response) === "EXIT"){
        return false;
    }
    if(preg_match('/^Length: (\d+)$/', trim($response), $matches) === 1){
        $length= $matches[1];
        $file_content=socket_read($socket,$length);
        echo "$file_content\n";
    }

    sleep(1); 
    return true;
}
$ison=true;
while($ison){
    if(@$response){
    echo $response;
    }// Sample commands to send to the server
    echo "Enter Command: ";
    $ison=send_command($client_socket,readline()."\r\n");


}

socket_close($client_socket);
echo "Connection closed.\n";

?>
