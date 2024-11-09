<?php

$server_ip = "127.0.0.1";
$server_port = 12345;
$max_clients = 4;  // Maksimumi i klientëve që mund të lidhen në të njëjtën kohë
$client_sockets = []; // Lista e klientëve të lidhur
$waiting_queue = []; // Lista e klientëve që presin për të u lidhur
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
            // Prano klientin dhe e shto në listën e klientëve të lidhur
            $client_sockets[] = $new_socket;
            $client_ip = '';
            socket_getpeername($new_socket, $client_ip);
            echo "Lidhje e re nga klienti me IP: $client_ip\n";
            log_request("Lidhje e re nga klienti me IP: $client_ip");
        } else {
            // Nëse serveri është i plotë, shto klientin në radhën e pritjes
            $waiting_queue[] = $new_socket;
            echo "Serveri është i plotë, lidhja u shtua në radhën e pritjes\n";
            socket_write($new_socket,"Serveri eshte plote. Jeni vendosur ne pritje",1049);
        }
        unset($read_sockets[array_search($server_socket, $read_sockets)]);
    }

    // Menaxhon mesazhet e dërguara nga klientët
    foreach ($read_sockets as $socket) {
        $data = @socket_read($socket, 1024, PHP_NORMAL_READ);
        if ($data === false) {
            // Mbyll lidhjen nëse klienti largohet ose dërgon një sinjal për të përfunduar lidhjen
            $index = array_search($socket, $client_sockets);
            if ($index !== false) {
                unset($client_sockets[$index]);
            }
            socket_close($socket);
            echo "Një klient është larguar\n";

            // Menaxho klientët që presin në radhë (dhe prano një nga ata)
            if (!empty($waiting_queue)) {
                $next_socket = array_shift($waiting_queue); // Merr klientin e parë në radhë
                $client_sockets[] = $next_socket;
                $client_ip = '';
                socket_getpeername($next_socket, $client_ip);
                echo "Klienti nga radhë me IP: $client_ip u pranua\n";
                log_request("Klienti nga radhë me IP: $client_ip u pranua");
            }

            continue;
        }

        // Ruaj mesazhin e klientit dhe dërgoje përgjigjen
        $data = trim($data);
        if ($data) {
            echo "Mesazh nga klienti: $data\r\n";
            log_request("Mesazh nga klienti: $data");

            // Shkrimi i mesazhit për monitorim
            file_put_contents($messages_file, "Mesazh nga klienti: $data\r\n", FILE_APPEND);

            if ($data === "READ_FILE") {
                // Dërgon përmbajtjen e një file-i te klienti
                $file_content = file_get_contents("server_file.txt")."\r\n";
                socket_write($socket, $file_content, strlen($file_content));
            } elseif ($data === "WRITE_FILE") {
                // Për klientin me qasje të plotë, shton mesazh në file-in e serverit
                file_put_contents("server_file.txt", "Shtuar nga klienti me privilegje të plota\n", FILE_APPEND);
                socket_write($socket, "Shkrimi përfundoi me sukses", 1024);
            } else {
                socket_write($socket, "Komanda e panjohur", 1024);
            }
        }
    }
}

socket_close($server_socket);
?>
