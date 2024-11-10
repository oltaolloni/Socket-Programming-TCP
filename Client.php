<?php
echo "\033[0;36mEnter Server IP:\033[0m ";
$server_ip = readline();
echo "\033[0;36mEnter Port:\033[0m ";
$server_port = readline();
$max_wait_time = 60;
$start_time = time();

function create_socket() {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
    }
    return $socket;
}

$client_socket = create_socket();  

echo "Duke u kyqur ne server...\n";

while (true) {
$connection = socket_connect($client_socket, $server_ip, $server_port);

if ($connection === false) {
    die("Unable to connect to server: " . socket_strerror(socket_last_error()) . "\n");
}

    // Kontrollo pergjigjen fillestare nga serveri
    $initial_response = socket_read($client_socket, 1024);
    if (strpos($initial_response, "FULL_SERVER") !== false) {
        if (($current_time - $start_time) > $max_wait_time) {
            socket_close($client_socket);
            exit;  
        }
        echo "Serveri është i plotë. Duke provuar... \n";
        sleep(15); 
        socket_close($client_socket);  // Mbyllim socketin dhe provojme perseri
        $client_socket = create_socket(); 
        continue;
    } elseif (strpos($initial_response, "CONNECTED") !== false) {
        echo "Connected to server at $server_ip:$server_port\n";
        break;
    } else {
        echo "Mesazh i papritur nga serveri: $initial_response\n";
        socket_close($client_socket);
        exit;
    }
}

// Dergimi i komandes ne server
function send_command($socket, $command) {

    $write_result = @socket_write($socket, $command, strlen($command));
    if ($write_result === false) {
        echo "\033[0;31mKomanda deshtoj ne dergim. Serveri mund te jete ndalur\033[0m\n";
        return false;
    }
    $length=1049;
    // Merr pergjigjen
    $response = socket_read($socket,$length);

    if ($response === false || $response === '') {
        echo "\033[0;31mServeri u qkyq.\033[0m\n";
        return false;
    }

    // Kontrollo nese serveri ka derguar TIMEOUT mesazh
    if (strpos($response, "TIMEOUT") !== false) {
        echo "\033[0;31m$response\033[0m\n"; 
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

$ison = true;
while ($ison) {
    echo "\033[0;32mEnter Command:\033[0m ";
    $command = readline();

    // Kontrollo nese serveri ka mbyllur lidhjen
    if (strpos($command, "TIMEOUT") !== false) {
        echo "Lidhja është mbyllur për shkak të inaktivitetit.\n";
        $ison = false;
        break;
    }

    if (trim($command) === '') {
        $ison = send_command($client_socket, "STATUS\n");
    } else {
        $ison = send_command($client_socket, $command . "\r\n");
    }
}

socket_close($client_socket);
echo "\033[0;35mLidhja u mbyll\033[0m\n";

?>