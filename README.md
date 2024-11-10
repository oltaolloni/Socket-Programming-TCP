# TCP Socket Connection using PHP

Ky program përfshin një server dhe disa klientë që lidhen me të përmes protokollit TCP duke përdorur sockets në PHP. Programi lejon komunikimin mes serverit dhe klientëve, ku një klient mund të kenë qasje të plotë (write, read, execute) dhe të tjerët vetëm për lexim (read).

## Hapat për Ekzekutimin e Programit

### 1. Aktivizimi i PHP sockets
Për të ekzekutuar këtë program, duhet të keni të instaluar dhe të aktivizuar **PHP**. 
1. Gjeni dhe hapni skedarin `php.ini`. Ai ndodhet në:
   - Për XAMPP: `C:\xampp\php\php.ini`
   - Për server të personalizuar: `C:\Program Files\PHP\php.ini`
3. Kërkoni për linjën që thotë `;extension=sockets` dhe hiqni **pikën e presjes** (`;`) para saj për ta aktivizuar rreshtin:
   extension=sockets
4. Pasi ta keni ndryshuar, ruani skedarin php.ini
5. Pathi ku kemi të instaluar PHP shembull `C:\xampp\php` duhet shtuar në Environment Variables të sistemit

### 2. Komanda për Ekzekutimin e Serverit dhe Klientëve
Pasi të keni konfigurimin e nevojshëm, mund të ekzekutoni programin në Command Prompt.
1. Lëvizni në pathin ku ndodhet .php file-i për ekzekutim me komandën `cd "<PATH>"`
2. Ekzekutoni me komandat `php Server.php` dhe `php Client.php`

## Komandat e Klientëve

#### Komandat për Klientin e thjeshtë

- **`HELP `**: Liston komandat që mund të ekzekutoje klienti.
- **`READ [path/to/file]`**: Lexon përmbajtjen e një file të specifikuar në server. Klienti mund të shohë përmbajtjen, por nuk mund të bëjë modifikime.
- **`SUPER [ADMIN_CODE] `**: Komanda për tu qasur si admin.
- **`EXIT `**: Mbyll lidhjen me serverin.
  
#### Komandat për Klientin me Qasje të Plotë

- **`HELP `**: Liston komandat që mund të ekzekutoje klienti me casje të plotë.
- **`READ [path/to/file]`**: Lexon përmbajtjen e një file të specifikuar në server.
- **`WRITE [path/to/file] [content] `**: Shkruan në një file të specifikuar në server.
- **`EXEC [comand] `**: Ekzekuton kod të sistemit ku operon serveri.
- **`EXIT `**: Mbyll lidhjen me serverin.
