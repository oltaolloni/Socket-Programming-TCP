<?php

$server_ip='127.0.0.1';
$server_port = 12345;
$max_clients = 4;  
$client_sockets = []; 
$timedOutClients = []; // Array to store timed-out clients
$log_file = "server_logs.txt";
$messages_file = 'client_messages.txt';
$admin_code="RANDOM123";
$timeout = 180;

// Krijimi i socket-it
$server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$server_socket) {
    die("Nuk u krijua socket-i: " . socket_strerror(socket_last_error()) . "\n");
}

socket_bind($server_socket, $server_ip, $server_port) or die("Nuk u arrit lidhja me IP dhe portin\n");
socket_listen($server_socket) or die("Serveri nuk mund të dëgjojë për lidhjet\n");

echo "Serveri po dëgjon në IP: $server_ip dhe portin: $server_port\n";

// Funksion per shkrimin e kerkesave
function log_request($message) {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

while (true) {
    // Kontrollon lidhjet e reja dhe i pranon
    $read_sockets = array_column($client_sockets, 'socket');
    $read_sockets[] = $server_socket;
    $write = null;
    $except = null;

    if (socket_select($read_sockets, $write, $except, 0) < 1) {
        continue;
    }

    if (in_array($server_socket, $read_sockets)) {
        if (count($client_sockets) < $max_clients) {
            if (array_key_exists($client['ip'], $timedOutClients)) {
                $client = $timedOutClients[$client['ip']]; // Restore the timed-out client connection
                unset($timedOutClients[$client['ip']]); // Remove from timed-out clients list
            } else {
                $new_socket = socket_accept($server_socket);
            $client_ip = '';
            socket_getpeername($new_socket, $client_ip);
            $client_sockets[] = [
                'socket' => $new_socket,
                'isAdmin' => false,
                'ip' => $client_ip,
                'lastActivity'=>time()
            ];
            echo "\033[0;36mLidhje e re nga klienti me IP:\033[0m " . $client_sockets[count($client_sockets)-1]['ip'] . "\n";
            socket_write($new_socket, "CONNECTED\n", 1024);
            log_request("Lidhje e re nga klienti me IP: " . $client_sockets[count($client_sockets)-1]['ip']);
            }
        } else {
           $temp_socket = socket_accept($server_socket);
           if ($temp_socket) {
               echo "Serveri është i plotë, lidhja u shtua në radhën e pritjes\n";
               socket_write($temp_socket, "\033[0;36mFULL_SERVER\033[0m", 1024);  // Dergon mesazhin "FULL_SERVER"
               socket_close($temp_socket);  
           }
       }
       unset($read_sockets[array_search($server_socket, $read_sockets)]);
   }

   foreach ($client_sockets as $key => $client) {
    if (time() - $client['lastActivity'] > $timeout) {
        echo "Klienti me IP: {$client['ip']} është larguar për shkak të inaktivitetit.\n";
        log_request("Klienti me IP: {$client['ip']} është larguar për shkak të inaktivitetit.");
        $timedOutClients[$client['ip']] = $client;
        socket_write($client['socket'], "TIMEOUT.\n", 1024);
        
        // Mbyll lidhjen dhe largo klientin nga lista
        socket_close($client['socket']);
        unset($client_sockets[$key]);
        unset($read_sockets[array_search($server_socket, $read_sockets)]);

    }
    }

    $client_sockets = array_values($client_sockets); 

    foreach ($read_sockets as $socket) {
        $data = @socket_read($socket, 1024, PHP_NORMAL_READ);
        if ($data === false) {
         
            $index = array_search($socket, array_column($client_sockets, 'socket'));
            if ($index !== false) { 
                unset($client_sockets[$index]);
                $client_sockets = array_values($client_sockets); 
                echo "\033[0;35mNjë klient është larguar\033[0m\n";
                log_request("Një klient është larguar");
            }
            socket_close($socket); 
                    }

        // Ruaj mesazhin e klientit dhe dergoje pergjigjen
        $data = trim($data);
        if ($data) {
            echo "Mesazh nga klienti:\033[0;32m $data\033[0m\r\n";
            log_request("Mesazh nga klienti: $data");

            // Shkrimi i mesazhit 
            file_put_contents($messages_file, "Mesazh nga klienti: $data\r\n", FILE_APPEND);
            $index = array_search($socket, array_column($client_sockets, 'socket'));
            if ($index !== false) {
               if(!@$client_sockets[$index]['isAdmin']){
                sleep(3);
               }
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
                            if(preg_match('/^EXEC\s+(\S+)(?:\s+(\S+))?$/', $data, $matches) === 1){
                                if (@$client_sockets[$index]['isAdmin']) {
                                    $command= $matches[1];
                                    $optional= $matches[2];
                                    if ($optional!==null){
                                        $output = shell_exec(trim($command." ".$optional));
                                    }else{
                                    $output = shell_exec(trim($command));
                                    }

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
                        $filename = $matches[1] . '.txt';  
                        $content = $matches[2];  
                    
                        // Check if the user is an admin
                        if (@$client_sockets[$index]['isAdmin']) {
                          
                            if (!file_exists($filename)) {
                                file_put_contents($filename, $content . "\n");
                                socket_write($socket, "Fajlli '$filename' u krijua dhe permbajtja u shenua.\n", 1024);
                            } else {
                                
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
                        $client_sockets = array_values($client_sockets); 
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