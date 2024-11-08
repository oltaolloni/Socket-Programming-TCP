<?php

$server_ip = "127.0.0.1";
$server_port = 12345;

// Krijimi i socket-it të klientit
$client_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($client_socket, $server_ip, $server_port) or die("Nuk u arrit lidhja me serverin\n");

echo "Klienti me akses të plotë është lidhur me serverin\n";

// Dërgimi i një mesazhi te serveri
$message = "Mesazh nga klienti me akses të plotë";
socket_write($client_socket, $message, strlen($message));

// Leximi i përgjigjes nga serveri
$response = socket_read($client_socket, 1024);
echo "Përgjigja nga serveri: $response\n";

// Dërgon komandën për të lexuar një file
socket_write($client_socket, "READ_FILE", 1024);
$response = socket_read($client_socket, 2048);
echo "Përmbajtja e file-it: $response\n";

// Dërgon komandën për të shkruar në file
socket_write($client_socket, "WRITE_FILE", 1024);
$response = socket_read($client_socket, 1024);
echo "Përgjigja nga serveri: $response\n";

socket_close($client_socket);

?>