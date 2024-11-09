<?php

// echo "\033[0;36mEnter Server IP:\033[0m ";
// $server_ip = readline();
$server_ip='127.0.0.1';
// $server_port = rand(10000, 65535);
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
            $client_ip = '';
            socket_getpeername($new_socket, $client_ip); // Retrieve client IP
            $client_sockets[] = [
                'socket' => $new_socket,
                'isAdmin' => false,
                'ip' => $client_ip
            ];
            echo "\033[0;36mLidhje e re nga klienti me IP:\033[0m " . $client_sockets[count($client_sockets)-1]['ip'] . "\n";
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
                $client_sockets = array_values($client_sockets); // Reindex to avoid gaps
                echo "\033[0;35mNjë klient është larguar\033[0m\n";
                log_request("Një klient është larguar");

                // Handle waiting queue by accepting the next client if there is space
                if (!empty($waiting_queue)) {
                    $next_socket = array_shift($waiting_queue); // Get the first client in queue
                    $client_sockets[] = [
                        'socket' => $next_socket,
                        'isAdmin' => false,
                        'ip' => ''
                    ];
                    socket_getpeername($next_socket, $client_sockets[count($client_sockets) - 1]['ip']);
                    echo "Klienti nga radhë me IP: " . $client_sockets[count($client_sockets) - 1]['ip'] . " u pranua\n";
                    log_request("Klienti nga radhë me IP: " . $client_sockets[count($client_sockets) - 1]['ip'] . " u pranua");
                }
            }
            socket_close($socket); // Close the socket after removing from the array
                    }

        // Ruaj mesazhin e klientit dhe dërgoje përgjigjen
        $data = trim($data);
        if ($data) {
            echo "Mesazh nga klienti:\033[0;32m $data\033[0m\r\n";
            log_request("Mesazh nga klienti: $data");

            // Shkrimi i mesazhit për monitorim
            file_put_contents($messages_file, "Mesazh nga klienti: $data\r\n", FILE_APPEND);
            $index = array_search($socket, array_column($client_sockets, 'socket'));
            if ($index !== false) {
                switch ($data) {
                    case  preg_match('/^READ/', $data, $matches) === 1:
                        if(preg_match('/^READ\s+(\S+).txt$/', $data, $matches) === 1){
                        $file= $matches[1].".txt";
                        if(!file_exists($file)){
                            socket_write($socket,"File nuk u gjet\n",1049);
                            break;
                        }
                        $file_content = file_get_contents($file) . "\r\n";
                        $length = strlen($file_content);
                        socket_write($socket,"Length: $length",1049);
                        socket_write($socket, $file_content, $length);}
                        else{
                            socket_write($socket, "\033[1;33mUsage\033[0m: READ filename.txt\n", 1024);
                        }
                        break;

                        case  preg_match('/^EXEC/', $data, $matches) === 1:
                            if(preg_match('/^EXEC\s+(\S+)$/', $data, $matches) === 1){
                                if (@$client_sockets[$index]['isAdmin']) {
                                    $command= $matches[1];
                                    $output = shell_exec(trim($command)); // Use 'ls' for Linux
                                    $length = strlen($output);
                                    socket_write($socket,"Length: $length",1049);
                                    socket_write($socket, $output, $length);
                                }
                                else{
                                    socket_write($socket,"\033[0;31mNuk ke privilegjet e admin\033[0m\n",1024);
                                }
                            }
                            else{
                                socket_write($socket, "\033[1;33mUsage\033[0m: EXEC command\n", 1024);
                            }
                            break;

                    case preg_match('/^WRITE/', $data, $matches) === 1:
                        if ( preg_match('/^WRITE\s+(\S+)\.txt\s+([\s\S]+)$/', $data, $matches) === 1){
                        $filename = $matches[1] . '.txt';  // Get the filename from the regex capture
                        $content = $matches[2];  // Get the content to write
                    
                        // Check if the user is an admin
                        if (@$client_sockets[$index]['isAdmin']) {
                            // Check if the file exists
                            if (!file_exists($filename)) {
                                // If the file doesn't exist, create it
                                file_put_contents($filename, $content . "\n");
                                socket_write($socket, "Fajlli '$filename' u krijua dhe permbajtja u shenua.\n", 1024);
                            } else {
                                // If the file exists, append content to it
                                file_put_contents($filename, $content . "\n", FILE_APPEND);
                                socket_write($socket, "Shenimi ne '$filename' u krye me sukses.\n", 1024);
                            }
                        } else {
                            socket_write($socket, "\033[0;31mNuk ke privilegjet e admin\033[0m", 1024);
                        }
                    }
                    else{
                        socket_write($socket, "\033[1;33mUsage\033[0m: WRITE filename.txt Content\n", 1024);
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
                                socket_write($socket, "\033[0;36m*Tani jeni admin*\033[0m\n");
                            } else {
                                socket_write($socket, "\033[0;31mInvalid SUPER code.\033[0m\n");
                            }
                        }
                        break;

                    case "EXIT":
                        socket_write($socket, "EXIT\n");
                        unset($client_sockets[$index]);
                        $client_sockets = array_values($client_sockets); // Reindex array to avoid gaps
                        socket_close($socket);
                        echo "\033[0;35mKlienti u largua me komanden EXIT\033[0m\n";
                        break;
                    
                    case  "HELP":
                        if (@$client_sockets[$index]['isAdmin']) {
                            socket_write($socket, "Komandat e lejuara:\n
                            \033[1;33mHELP\033[0m - Shfaq Komandat\n
                            \033[1;33mREAD <file>\033[0m- Lexon nga nje file\n
                            \033[1;33mWRITE <file> <Content>\033[0m- Shkruan ne nje fajll\n
                            \033[1;33mEXEC <command>\033[0m - ekzekuton kod te sistemit ku operon serveri\n
                            \033[1;33mEXIT\033[0m- E mbyll lidhjen\n", 1024);
                        } else {
                            socket_write($socket, "Komandat e lejuara:\n
                            \033[1;33mHELP\033[0m - Shfaq Komandat\n
                            \033[1;33mREAD <file>\033[0m- Lexon nga nje file\n
                            \033[1;33mSUPER <ADMIN-CODE>\033[0m- Ju bene admin nese nuk ka ndonje\n
                            \033[1;33mEXIT\033[0m- E mbyll lidhjen\n", 1024);
                        }
                        break;

                        case  "STATUS":
                            if (@$client_sockets[$index]['isAdmin']) {
                                socket_write($socket, "\033[0;36m*Ju jeni admin*\033[0m", 1024);
                            } else {
                                socket_write($socket, "\033[1;33m*Ju jeni klient*\033[0m", 1024);
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
