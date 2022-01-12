<?php
ini_set('display_errors', 0);
ini_set("log_errors", 1);
ini_set("error_log", "./store/error.txt");

date_default_timezone_set("Europe/Oslo");


header("Content-type: application/json");

//sleep(1);

// "databasen" lagres i denne filen
$dbFile = "./store/data.json";

// dette er passordet - brukes slik: http://....../api.php?auth=CHANGE_THIS_TO_A_RANDOM_STRING
$auth = "CHANGE_THIS_TO_A_RANDOM_STRING";

// sjekk passordet i query param
if ($_GET['auth'] != $auth) {
    http_response_code(401);
    die(json_encode(array("error" => "Feil passord. Set 'auth' i query param.")));
}


// hvis ikke http_response_code metoden er definert, så definer den her
if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL)
    {

        if ($code !== NULL) {

            switch ($code) {
                case 100:
                    $text = 'Continue';
                    break;
                case 101:
                    $text = 'Switching Protocols';
                    break;
                case 200:
                    $text = 'OK';
                    break;
                case 201:
                    $text = 'Created';
                    break;
                case 202:
                    $text = 'Accepted';
                    break;
                case 203:
                    $text = 'Non-Authoritative Information';
                    break;
                case 204:
                    $text = 'No Content';
                    break;
                case 205:
                    $text = 'Reset Content';
                    break;
                case 206:
                    $text = 'Partial Content';
                    break;
                case 300:
                    $text = 'Multiple Choices';
                    break;
                case 301:
                    $text = 'Moved Permanently';
                    break;
                case 302:
                    $text = 'Moved Temporarily';
                    break;
                case 303:
                    $text = 'See Other';
                    break;
                case 304:
                    $text = 'Not Modified';
                    break;
                case 305:
                    $text = 'Use Proxy';
                    break;
                case 400:
                    $text = 'Bad Request';
                    break;
                case 401:
                    $text = 'Unauthorized';
                    break;
                case 402:
                    $text = 'Payment Required';
                    break;
                case 403:
                    $text = 'Forbidden';
                    break;
                case 404:
                    $text = 'Not Found';
                    break;
                case 405:
                    $text = 'Method Not Allowed';
                    break;
                case 406:
                    $text = 'Not Acceptable';
                    break;
                case 407:
                    $text = 'Proxy Authentication Required';
                    break;
                case 408:
                    $text = 'Request Time-out';
                    break;
                case 409:
                    $text = 'Conflict';
                    break;
                case 410:
                    $text = 'Gone';
                    break;
                case 411:
                    $text = 'Length Required';
                    break;
                case 412:
                    $text = 'Precondition Failed';
                    break;
                case 413:
                    $text = 'Request Entity Too Large';
                    break;
                case 414:
                    $text = 'Request-URI Too Large';
                    break;
                case 415:
                    $text = 'Unsupported Media Type';
                    break;
                case 500:
                    $text = 'Internal Server Error';
                    break;
                case 501:
                    $text = 'Not Implemented';
                    break;
                case 502:
                    $text = 'Bad Gateway';
                    break;
                case 503:
                    $text = 'Service Unavailable';
                    break;
                case 504:
                    $text = 'Gateway Time-out';
                    break;
                case 505:
                    $text = 'HTTP Version not supported';
                    break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;

        } else {

            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);

        }

        return $code;

    }
}


// Det som skal returneres
$ret = "";

// Feilmelding som skal returneres i stedenfor
$error = "";

// sync_file sammen med bruk ac flock(...) er det som garanterer at
// kun én request av gangen får tilgang til databasefilen
$fp = fopen('./store/sync_file', 'r+');
try {


    if (flock($fp, LOCK_EX)) {

        try {


            // alle GET requests vil returnere hele databasen
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $db = json_decode(file_get_contents($dbFile), true);
                if ($db['version'] > $_GET['version']) {
                    $ret = file_get_contents($dbFile);
                } else {
                    $ret = json_encode(array("version" => -1));
                }

            } else if ($_SERVER['REQUEST_METHOD'] === 'POST') { // POST request brukes for å modifisere innholdet i databasen

                // først leser vi innholdet i POST requesten
                $data = json_decode(file_get_contents('php://input'), true);

                // så laster vi database filen og parser den som json
                $db = json_decode(file_get_contents($dbFile), true);
                $db['version']++;

                // query param 'action' brukes for å bestemme hvilken operasjon som skal gjennomføres
                if ($_GET['action'] === 'set') {
                    $db['data'][$data['key']] = $data['value'];

                } elseif ($_GET['action'] === 'unset') {
                    unset($db['data'][$data['key']]);

                } elseif ($_GET['action'] === 'add') {
                    $data['value']['ref'] = $data['value']['id'] ?? null ;
                    $data['value']['id'] = $db['version'];
                    $data['value']['created'] =  date("c");
                    $data['value']['lastModified'] =  date("c");

                    if($db['data'][$data['key']] == null){
                        $db['data'][$data['key']] = [];
                    }

                    array_push($db['data'][$data['key']], $data['value']);

                } elseif ($_GET['action'] === 'update') {
                    $data['value']['id'] = $data['id'];
                    $data['value']['lastModified'] =  date("c");

                    foreach ($db['data'][$data['key']] as $key => $value) {
                        if ($value['id'] === $data['id']) {
                            $db['data'][$data['key']][$key] = $data['value'];
                        }
                    }

                } elseif ($_GET['action'] === 'delete') {
                    foreach ($db['data'][$data['key']] as $key => $value) {
                        if ($value['id'] === $data['id']) {
                            unset ($db['data'][$data['key']][$key]);
                        }
                    }

                    $db['data'][$data['key']] = array_values($db['data'][$data['key']]);

                } else if ($_GET['action'] === 'reload') {
                    $db['reload'] = $data['reload'];

                }

                // lagre databasen
                file_put_contents($dbFile,  json_encode($db, JSON_PRETTY_PRINT));

                $ret = json_encode($db);

            }
        } finally {
            // nå er vi ferdige og vi kan låse opp sync filen slik at neste request kan håndteres
            flock($fp, LOCK_UN);
        }

    } else {
        // bruken av LOCK_EX når vi låser sync filen gjør at koden som regel venter hvis filen
        // allerede er låst, så det er ytterst sjeldent at feilen under vil oppstå
        error_log("Kunne ikke lagre endringene i databasen! flock returnerte false");
        http_response_code(500);
        die(json_encode(array("error" => "Kunne ikke lagre endringene i databasen. Prøv igjen!")));
    }
} finally {
    fclose($fp);
}

if ($error != "") {
    http_response_code(500);
    die(json_encode(array("error" => $error)));
} else {
    // returnere de oppdaterte dataene til frontend
    echo $ret;
}


?>
