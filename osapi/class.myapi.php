<?php
require_once('../main.inc.php');
include_once INCLUDE_DIR . 'class.api.php';
include_once INCLUDE_DIR . 'class.ticket.php';
include_once INCLUDE_DIR . "class.json.php";


class MyAPI extends ApiController
{
    function getLoadUrl($fileId)
    {
        /**
         * Генери
         * рует ссылку на скачивание/просмотр изображения изображения
         * ссылка должа быть на существующий файл get_picture.php который обрабатывает гет запрос 
         * @param $fileId - Тип int id записи из таблицы FILE_TABLE
         * @return url для скачивания изображения
         */

        $encryptedId = @openssl_encrypt($fileId, $this->apiConfig['encrypt_method'], $this->apiConfig['encrypt_key']);
        $id = openssl_decrypt($encryptedId, $this->apiConfig['encrypt_method'], $this->apiConfig['encrypt_key']); // расшифровка
        $url = $this->apiConfig['picture_url'] . '?id=' . urlencode($encryptedId) . '&orig = ' . $id;
        return $url;
    }

    static function getPicture($fileId)
    {
        /**
         * Функция выполняет загрузку файла по введенному id файла
         * @param $fileId - id файла из таблицы FILE_TABLE
         * Выводит на экран изображение остальные все выводы убирает
         */
        $file = AttachmentFile::lookup((int)$fileId);
        $minage = @$options['minage'] ?: 43200;
        $gmnow = Misc::gmtime() +  $options['minage'];
        $expires = $gmnow + 86400 - ($gmnow % 86400);
        // $upload = $file->download($file->getName(), '', $expires); // для загрузки
        $upload = $file->display(); // для показа на экране
    }

    static $commonParams = array(
        'ownerMessageType' => 'M',
        'htmlFormat' => 'html',
        'sqlFunctionNow' => 'NOW()',
        'defaultDateTime' => '0000-00-00 00:00:00',
        'closedTicketStatus' => 'closed'
    );

    function __construct($url, $key, $apiConfig)
    {
        $this->url = $url;
        $this->key = $key;
        $this->apiConfig = $apiConfig;
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

    function attacmentsUpload($data)
    {
        /**
         * Функция берет из json запроса $data['attacments']
         * Преобразует в данные для отправки и отправляет на сервер
         */
        $errors = array();
        if (!is_array($data)) {
            $errors[] = 'параметр это массив ["attacments"]';
        }
        foreach ($data as &$value) {
            $newData = reset($value);
            $contents = Format::parseRfc2397($newData, 'utf-8', false);
            $value = array(
                "data" => $contents['data'],
                "type" => $contents['type'],
                "name" => key($value),
            );
        }
        $tform = TicketForm::objects()->one()->getForm();
        $messageField = $tform->getField('message');
        $fileField = $messageField->getWidget()->getAttachments();
        foreach ($data as &$file) {
            if ($file['encoding'] && !strcasecmp($file['encoding'], 'base64')) {
                if (!($file['data'] = base64_decode($file['data'], true)))
                    $file['error'] = sprintf(
                        __('%s: Poorly encoded base64 data'),
                        Format::htmlchars($file['name'])
                    );
            }
            // Validate and save immediately
            try {
                $F = $fileField->uploadAttachment($file); // создает и загружает файл
                $file['id'] = $F->getId(); // id таблицы file
            } catch (FileUploadError $ex) {
                $name = $file['name'];
                $file = array();
                $file['error'] = Format::htmlchars($name) . ': ' . $ex->getMessage();
            }
        }
        return $file['id'];
        unset($file);
    }


    function addToTicket($postData)
    {
        /**
         * Метод добавляет в существующий тикет ответ на него
         * В случае успешного выполнения возвращает sucsessfull
         */
        try {
            function_exists('json_encode') or die('JSON support required');
            $this->createUser($postData['name'], $postData['email']); // создет пользоваетля если его не было до этого
            if (!empty($postData['attachments'])) {
                $fileId = $this->attacmentsUpload(array($postData['attachments']));
            }

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
                if (!empty($postData['attachments'])) {
                    $sql = sprintf(
                        "INSERT INTO %s (id, object_id, type, file_id, name, inline, lang) VALUES 
                        (NULL, LAST_INSERT_ID(), 'H', %d, NULL, 0, NULL)",
                        ATTACHMENT_TABLE,
                        $fileId
                    );
                    db_query($sql);
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
        /**
         * Тут решено не изобретать велосипед и отдать встроенной апишке (работает только создание тикета)
         */
        $config = array(
            'url' => $this->url . 'tickets.json',
            'key' => $this->key,
        );

        $validateData = $data;
        if (!empty($data['attachments']))
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
         * @param $data['username'] - имя пользователя запросившего историю
         * @param $data['ticketNumber'] номер запрашиваемого тикета 
         * @param $data['ignored'] - флаг, осуществалять ли проверку или нет если 1, то проверка на принадлежность пользователя к ти кету осуществляется
         * 
         * @return 0 или оформленный массив сообщений
         */
        $ticketId = Ticket::getIdByNumber((int)$data['ticketNumber']);
        $query = sprintf(
            "SELECT th.*, u.name, u.default_email_id, att.file_id FROM %s AS th 
            LEFT JOIN %s AS u ON th.user_id = u.id 
            LEFT JOIN %s AS att ON th.id = att.object_id
            WHERE th.thread_id = %d ORDER BY th.id ASC; ",
            THREAD_ENTRY_TABLE,
            USER_TABLE,
            ATTACHMENT_TABLE,
            db_input($ticketId)
        );
        $r = db_query($query);
        $result = db_assoc_array($r);
        $message = array();
        foreach ($result as $element) {
            if (is_numeric($element['file_id'])) {
                $attacment = "\nВложение: " . $this->getLoadUrl((string)$element['file_id']);
            } else {
                $attacment = '';
            }
            $message[] = "Poster: " . $element['name'] . "\nDate: " . $element['created'] . "\nMessage: " .
                $element['body'] . $attacment;
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

    function getTopicList()
    {
        /**
         * Функция получает из бд все топики
         */
        $query = sprintf("SELECT topic_id, topic FROM %s", TOPIC_TABLE);
        $r = db_query($query);
        $r = db_assoc_array($r);
        $result = "";
        foreach ($r as $row) {
            $result .="'". $row["topic"] . "' : '" .$row["topic_id"] . "', \n";
        }
        return $result;
    }
}
