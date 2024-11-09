<?php

$server_ip = "127.0.0.1";
$server_port = 12345;
$max_clients = 4;  // Maksimumi i klientëve që mund të lidhen në të njëjtën kohë
$client_sockets = []; // Lista e klientëve të lidhur
$waiting_queue = []; // Lista e klientëve që presin për të u lidhur
$log_file = "server_logs.txt";
$messages_file = 'client_messages.txt';
$admin_code="RANDOM123";

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
    $read_sockets = array_column($client_sockets, 'socket');
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
            $client_sockets[] = [
                'socket' => $new_socket,
                'isAdmin' => false,
                'ip' => ''
            ];
            socket_getpeername($new_socket, $client_sockets[count($client_sockets)-1]['ip']);
            echo "Lidhje e re nga klienti me IP: " . $client_sockets[count($client_sockets)-1]['ip'] . "\n";
            log_request("Lidhje e re nga klienti me IP: " . $client_sockets[count($client_sockets)-1]['ip']);
        } else {
            // Nëse serveri është i plotë, shto klientin në radhën e pritjes
            $waiting_queue[] = $new_socket;
            echo "Serveri është i plotë, lidhja u shtua në radhën e pritjes\n";
            socket_write($new_socket, "Serveri eshte plote. Jeni vendosur ne pritje", 1049);
        }
        unset($read_sockets[array_search($server_socket, $read_sockets)]);
    }

    // Menaxhon mesazhet e dërguara nga klientët
    foreach ($read_sockets as $socket) {
        $data = @socket_read($socket, 1024, PHP_NORMAL_READ);
        if ($data === false) {
            // Mbyll lidhjen nëse klienti largohet ose dërgon një sinjal për të përfunduar lidhjen
            $index = array_search($socket, array_column($client_sockets, 'socket'));
            if ($index !== false) {
                unset($client_sockets[$index]);
            }
            socket_close($socket);
            echo "Një klient është larguar\n";

            // Menaxho klientët që presin në radhë (dhe prano një nga ata)
            if (!empty($waiting_queue)) {
                $next_socket = array_shift($waiting_queue); // Merr klientin e parë në radhë
                $client_sockets[] = [
                    'socket' => $next_socket,
                    'isAdmin' => false,
                    'ip' => ''
                ];
                socket_getpeername($next_socket, $client_sockets[count($client_sockets)-1]['ip']);
                echo "Klienti nga radhë me IP: " . $client_sockets[count($client_sockets)-1]['ip'] . " u pranua\n";
                log_request("Klienti nga radhë me IP: " . $client_sockets[count($client_sockets)-1]['ip'] . " u pranua");
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
            $index = array_search($socket, array_column($client_sockets, 'socket'));
            if ($index !== false) {
                switch ($data) {
                    case  preg_match('/^READ\s+(\S+).txt$/', $data, $matches) === 1:
                        $file= $matches[1].".txt";
                        $file_content = file_get_contents($file) . "\r\n";
                        $length = strlen($file_content);
                        socket_write($socket,"Length: $length",1049);
                        socket_write($socket, $file_content, $length);
                        break;

                    case preg_match('/^WRITE\s+(\S+)\.txt\s+([\s\S]+)$/', $data, $matches) === 1:
                        $filename = $matches[1] . '.txt';  // Get the filename from the regex capture
                        $content = $matches[2];  // Get the content to write
                    
                        // Check if the user is an admin
                        if ($client_sockets[$index]['isAdmin']) {
                            // Check if the file exists
                            if (!file_exists($filename)) {
                                // If the file doesn't exist, create it
                                file_put_contents($filename, $content . "\n");
                                socket_write($socket, "File '$filename' was created and content was written.\n", 1024);
                            } else {
                                // If the file exists, append content to it
                                file_put_contents($filename, $content . "\n", FILE_APPEND);
                                socket_write($socket, "Content was successfully written to '$filename'.\n", 1024);
                            }
                        } else {
                            socket_write($socket, "Nuk keni privilegje te adminit.\n", 1024);
                        }
                        break;

                    case preg_match('/^SUPER\s+(\S+)$/', $data, $matches) === 1:
                        $code = $matches[1];
                        // Check if there's already an admin
                        $admin_exists = false;
                        foreach ($client_sockets as $client) {
                            if ($client['isAdmin']) {
                                $admin_exists = true;
                                break;
                            }
                        }

                        if ($admin_exists) {
                            socket_write($socket, "Nuk mund te kete dy admins.\n");
                        } else {
                            if ($code === $admin_code) {
                                $client_sockets[$index]['isAdmin'] = true;
                                socket_write($socket, "Tani jeni admin.\n");
                            } else {
                                socket_write($socket, "Invalid SUPER code.\n");
                            }
                        }
                        break;

                    case "EXIT":
                        socket_write($socket, "EXIT\n");
                        echo "Client has been disconnected due to EXIT command\n";
                        break;
                    
                    case  "HELP":
                        if ($client_sockets[$index]['isAdmin']) {
                            socket_write($socket, "Komandat e lejuara:\n
                            HELP - Shfaq Komandat\n
                            READ <file>- Lexon nga nje file\n
                            WRITE <file> <Content>- Shkruan ne nje fajll\n
                            EXIT- E mbyll lidhjen\n", 1024);
                        } else {
                            socket_write($socket, "Komandat e lejuara:\n
                            HELP - Shfaq Komandat\n
                            READ <file>- Lexon nga nje file\n
                            SUPER <ADMIN-CODE>- Ju bene admin nese nuk ka ndonje\n
                            EXIT- E mbyll lidhjen\n", 1024);
                        }
                        break;

                    default:
                        socket_write($socket, "Komanda e panjohur\n", 1024);
                        break;
                }
            }
        }
    }
}

socket_close($server_socket);

?>
