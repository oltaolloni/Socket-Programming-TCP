<?php
echo "\033[0;36mEnter Server IP:\033[0m ";
$server_ip = readline();
echo "\033[0;36mEnter Port:\033[0m ";
$server_port = readline();
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
    
    if (!is_resource($socket) || socket_last_error($socket) != 0) {
        echo "\033[0;31mLidhja me serverin eshte terminuar.\033[0m\n";
        return false;
    }

    $write_result = socket_write($socket, $command, strlen($command));
    if ($write_result === false) {
        echo "\033[0;31mKomanda deshtoj ne dergim. Serveri mund te jete ndalur\033[0m\n";
        return false;  // Exit the loop if writing failed
    }
    $length=1049;
    // Get the server's response
    $response = socket_read($socket,$length);

    if ($response === false || $response === '') {
        echo "\033[0;31mServeri u qkyq.\033[0m\n";
        return false;
    }

    echo $response."\n";
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
    echo "\033[0;32mEnter Command:\033[0m ";
    $ison=send_command($client_socket,readline()."\r\n");


}

socket_close($client_socket);
echo "\033[0;35mLidhja u mbyll\033[0m\n";

?>
