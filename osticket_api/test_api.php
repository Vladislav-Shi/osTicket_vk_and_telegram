#!/usr/bin/php -q
<?php

$config = array(
    'url' => 'API_URL',
    'key' => 'API_KEY'
);
$data = array(
    'key'=> '',
    'args' => array(
        'ticketNumber'=> 728302,
        'name'=> 'USER',
        'email'=> 'id11111111@host.com',
        'message'=>  'TEST API',
        'ip'=> '127.0.0.1',
    )
);


/* 
 * Add in attachments here if necessary

$data['attachments'][] =
array('filename.pdf' =>
        'data:image/png;base64,' .
            base64_encode(file_get_contents('/path/to/filename.pdf')));
 */
$data['args']['attachments'][] =
array('syle_ftp.jpg' =>
        'data:image/jpg;base64,' .
            base64_encode(file_get_contents(__DIR__ . '/syle_ftp.jpg')));

// file_put_contents('./file.txt', print_r($data, true));
#pre-checks
function_exists('curl_version') or die('CURL support required');
function_exists('json_encode') or die('JSON support required');
#set timeout
set_time_limit(40);
#curl post
$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_URL, $config['url']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket API Client v1.7');
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:', 'X-API-Key: ' . $config['key']));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
// эти две строчик ниже для подключение к https. Отключает проверку SSL сертификата
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
print_r($result);
echo 'code: ' . $code . '<br>';
echo '<br> corl_err ' . curl_errno($ch) . '<br>';
curl_close($ch);
if ($code != 201)
    die('Unable to create ticket: ' . $result);

$ticket_id = (int) $result;
echo '<br>ticket_id: ' . $ticket_id;

# Continue onward here if necessary. $ticket_id has the ID number of the
# newly-created ticket

/*
Example  

curl -d "{}" -H "X-API-Key: 68AFA8405E8B569A1E8441C841182CFD" https://osticket.local/api/tickets.json

 */
?>