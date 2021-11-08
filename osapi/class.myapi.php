<?php
require_once('../main.inc.php');
include_once INCLUDE_DIR . 'class.api.php';
include_once INCLUDE_DIR . 'class.ticket.php';

class MyAPI
{
    // мой код 
    static $commonParams = array(
        'ownerMessageType' => 'M',
        'htmlFormat' => 'html',
        'sqlFunctionNow' => 'NOW()',
        'defaultDateTime' => '0000-00-00 00:00:00',
        'closedTicketStatus' => 'closed'
    );
    function MyAPI($url, $key)
    {
        $this->url = $url;
        $this->key = $key;
    }
    // Возвращает тикет по введенному id

    function callMethod($params)
    {
        /**
         * Вызывает методы класса
         */
        $result = call_user_func(array($this, $params['function']), $params['args']);
        return $result;
    }

    function checkTicketOwner($data)
    {
        /**
         * Проверяет наличие у пользователя билета
         * 
         * @param $username - имя проверяемого пользователя
         * @param $ticketNumber - проверяемый юилет
         * 
         * @return -1 или id билета
         */
        $ticket = MyAPI::getTicketByNumber((int)$data['ticketNumber']);
        if (!$ticket) {
            return -1;
        }
        if ($data['username'] != MyAPI::getTicketOwner($ticket)) {
            return -1;
        }
        return Ticket::getIdByNumber((int)$data['ticketNumber']);
    }

    static function getTicketByNumber($ticketNumber)
    {
        return Ticket::lookup(array('number' => $ticketNumber));
    }

    static function getTicketOwner(Ticket $ticket)
    {
        return $ticket->getName();
    }

    function getUserIdByEmail(string $email)
    {
        $fixEmail = $email;
        $sql = sprintf(
            "SELECT user_id 
        FROM %s WHERE address = '%s'
        LIMIT %d",
            USER_EMAIL_TABLE,
            $fixEmail,
            1
        );
        $r = db_query($sql);
        $r = db_fetch_array($r, MYSQLI_ASSOC);
        return $r["user_id"];
    }
    function validateParams($params)
    { //      print_r($params);   die();
        $errors = array();
        if (empty($params['email'])) {
            $errors[] = 'Отсутствует почта';
        }
        if (empty($params['message'])) {
            $errors[] = 'Отсутсвует сообщение';
        }
        if (!empty($params['ticketNumber'])) {
            // тут не забыть про привидение типов, а то в param только строки
            $id = Ticket::getIdByNumber((int)$params['ticketNumber']);
            if ($id <= 0) {
                $errors[] = "Тикет не найден";
            } else {
                //Проверка статуса, мб  тикет уже закрыт 
                $ticket = Ticket::lookup($id);
                $ticket_status = $ticket->getState();
                if ($ticket_status == MyAPI::$commonParams['closedTicketStatus']) {
                    $errors[] = "Тикет закрыт";
                }
            }
        } else {
            $errors[] = 'Не найден';
        }

        return $errors;
    }

    function addToTicket($postData)
    {
        try {
            function_exists('json_encode') or die('JSON support required');
            $this->createUser($postData['name'], $postData['email']); // создет пользоваетля если его не было до этого
            $errors = $this->validateParams($postData);
            if (empty($errors)) {
                $data['id'] =  Ticket::getIdByNumber((int)$postData['ticketNumber']);
                $data['userId'] =  $this->getUserIdByEmail($postData['email']);
                $data['staffId'] = 0;
                $data['poster'] = $postData['name'];
                $data['ip_address'] = $postData['ip'];
                $data['type'] = MyAPI::$commonParams['ownerMessageType']; //'M' - Message owne
                $data['flags'] = ThreadEntry::FLAG_BALANCED; // HTML does not need to be balanced on ::display()
                $data['body'] = $postData['message'];
                $data['html'] = MyAPI::$commonParams['htmlFormat']; //html
                $data['created'] = MyAPI::$commonParams['sqlFunctionNow']; //SqlFunction NOW();
                $data['updated'] = MyAPI::$commonParams['defaultDateTime'];
                $sql = 'INSERT INTO  ' . THREAD_ENTRY_TABLE . ' (`id`  ,`thread_id` ,`staff_id` ,`user_id` ,`type` ,`flags` ,`poster` ,`editor` ,`editor_type` ,`source` ,`title` ,`body` ,`format` ,`ip_address` ,`created` ,`updated`)
            VALUES (NULL ,  ' . $data['id'] . ',  ' . $data['staffId'] . ',  ' . $data['userId'] . ',  "' . $data['type'] . '",  ' . $data['flags'] . ',  "' . $data['poster'] . '", NULL , NULL , "API" , "" ,  "' . $data['body'] . '",  "' . $data['html'] . '",  "' . $data['ip_address'] . '",  ' . $data['created'] . ',  "' . $data['updated'] . '")'; 
            if (!$res = db_query($sql)) {
                    $message = ['status' => 'failed', 'message' => 'SQL query not executed'];
                }

                $message = ['status' => 'success', 'message' => 'Reply posted succesfully'];
            } else {
                $message = ['status' => 'failed', 'errors' => $errors];
            }
            $result_code = 200;
            // $this->response($result_code, json_encode($message), $contentType = "application/json");
            return json_encode($message);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $result = array('tickets' => array(), 'status_code' => 'FAILURE', 'status_msg' => $msg);
            return json_encode($result);
            // $this->response(500, json_encode($result), $contentType = "application/json");
        }
    }

    function createTicket($data)
    {
        $config = array(
            'url' => $this->url . 'tickets.json',
            'key' => $this->key,
        );

        $validateData = $data;
        $validateData['attachments'] = array($data['attachments']);

        function_exists('curl_version') or die('CURL support required');
        function_exists('json_encode') or die('JSON support required');
        $this->createUser($validateData['name'], $validateData['email']); // создет пользоваетля если его не было до этого
        #set timeout
        set_time_limit(40);
        #curl post
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $config['url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validateData));
        curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket API Client v1.7');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:', 'X-API-Key: ' . $config['key']));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // эти две строчик ниже для подключение к https. Отключает проверку SSL сертификата
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        echo 'code: ' . curl_error($ch) . "\n";
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code != 201)
            die('Unable to create ticket: ' . $result);
        $ticket_id = (int) $result;
        return '<br>ticket_id: ' . $ticket_id;
    }

    function getMessageStory($data)
    {
        $check = $this->checkTicketOwner($data);
        if ($check == -1) {
            return 0;
        }
        /**
         * Получает цепочку сообщений в массив
         * @param $username - имя пользователя запросившего историю
         * @param $ticketNumber номер запрашиваемого тикета 
         * @param $ignored - флаг, осуществалять ли проверку или нет если 1, то проверка на принадлежность пользователя к ти кету осуществляется
         * 
         * @return 0 или оформленный массив сообщений
         */
        $ticketId = Ticket::getIdByNumber((int)$data['ticketNumber']);
        $query = sprintf(
            "SELECT th.*, u.name, u.default_email_id FROM %s AS th LEFT JOIN %s AS u ON th.user_id = u.id 
            WHERE th.thread_id = %d ORDER BY th.id ASC; ",
            THREAD_ENTRY_TABLE,
            USER_TABLE,
            db_input($ticketId)
        );
        $r = db_query($query);
        $result = db_assoc_array($r);
        $message = array();
        foreach ($result as $element) {
            $message[] = "Poster: " . $element['name'] . "\nDate: " . $element['created'] . "\nMessage: " .
                $element['body'];
        }
        return implode("\n\n", $message);
    }

    /*
BEGIN
DECLARE mail_id INT;
DECLARE new_user_id INT;
INSERT INTO ost_user_email (user_id, flags, address) VALUES (0, 0, email);
SET mail_id = LAST_INSERT_ID();
INSERT INTO ost_user (org_id, default_email_id, status, name, created, updated) VALUES
(0, mail_id, 0, username, NOW(), NOW());
SET new_user_id = LAST_INSERT_ID();
REPLACE INTO ost_user_email (id, user_id, flags, address) VALUES 
     */

    function checkUser($name, $email)
    {
        /**
         * Функция ищет существуют ли записи с таким именем или мылом и возврщает true если есть хоть одна или false если нет ничего
         */
        $query = sprintf(
            "SELECT name FROM %s WHERE name = '%s' UNION SELECT address FROM %s WHERE address = '%s';",
            USER_TABLE,
            $name,
            USER_EMAIL_TABLE,
            $email
        );
        $r = db_count($query);
        if ($r > 0)
            return true;
        else
            return false;
    }
    function createUser($name, $emali)
    {
        /**
         * Создает пользователя и регистрирует его мыло 
         */
        $nameChecked = db_input($name);
        $emaliChecked = db_input($emali);
        $count = $this->checkUser($nameChecked, $emaliChecked);
        if (!$count) {
            $query = sprintf("CALL ADD_USER_AND_EMAIL('%s', '%s')", $emaliChecked, $nameChecked);
            $r = db_query($query);
        }
    }
}
