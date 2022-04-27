<?php
require_once('../main.inc.php');
include_once INCLUDE_DIR . 'class.api.php';
include_once INCLUDE_DIR . 'class.ticket.php';
include_once INCLUDE_DIR . 'class.json.php';
include_once 'orm.php';
include_once 'config.php';

class Api2Controller extends ApiController
{
    /**
     * Класс обрабатывающий все запросы к API
     */

    static $commonParams = array( // массив нужынх переменных (нужно для добавления в бд запсией)
        'ownerMessageType' => 'M',
        'htmlFormat' => 'html',
        'sqlFunctionNow' => 'NOW()',
        'defaultDateTime' => '0000-00-00 00:00:00',
        'closedTicketStatus' => 'closed'
    );

    public static function callMethod($url, $data)
    {
        /**
         * Функция которая должна вызывать метод по ввреденному урлу
         * @param url - последняя часть пути (getHistory из my.site/api2/getHistory)
         * @param data - array значений дял функции
         */
        return call_user_func(array('Api2Controller', $url), $data);
    }

    private static function getTicketOwner(Ticket $ticket)
    {
        /**
         * Возвращает имя владельца тикета
         */
        return $ticket->getName();
    }

    private static function getTicketByNumber($ticketNumber)
    {
        /**
         * Получает обьект класса билета по введенному номеру
         */
        return Ticket::lookup(array('number' => $ticketNumber));
    }
    private static function checkUser($name)
    {
        /**
         * Проверяет существует ли такой пользователь
         * @return true|false
         */
        $r = ApiOrm::getUserByName($name);
        if ($r > 0)
            return true;
        else
            return false;
    }
    private static function createUser($email, $name)
    {
        /**
         * Создает пользователя если он еще не существует
         * (пользователи могут быть с одинаковыми именами ИЛИ мылом)
         */
        if (!self::checkUser($name)) {
            ApiOrm::createUserProcedure();
            ApiOrm::createUser($name, $email);
        }
    }
    public static function getUserTickets($data)
    {
        /**
         * Функция возвращает номера всех заявок принадлежащих пользователю
         * @param $data['username'] - имя пользователя
         */
        $user_id =  ApiOrm::getUserByName($data['username']);
        $result = ApiOrm::getUserTickets($user_id['id']);
        if (!$result) {
            return json_encode(array('status' => 400, 'message' => 'Заявок не найдено!'));
        }
        $ticketNumber = array();
        foreach ($result as $el) {
            $ticketNumber[$el['number']] = $el['number'];
        }
        return json_encode(array('status' => 200, 'tickets' =>  $ticketNumber));
    }

    private static function validateParams($params)
    {
        /**
         * Валидация для добавления ответа
         */
        $errors = array();
        if (empty($params['name'])) {
            $errors[] = 'Отсутствует имя';
        }
        if (empty($params['message'])) {
            $errors[] = 'Отсутсвует сообщение';
        }
        if (!empty($params['ticketNumber'])) {
            // тут не забыть про привидение типов, а то в param только строки
            $id = Ticket::getIdByNumber((int)$params['ticketNumber']);
            if ($id <= 0) {
                $errors[] = 'Тикет не найден';
            } else {
                //Проверка статуса, мб  тикет уже закрыт 
                $ticket = Ticket::lookup($id);
                $ticket_status = $ticket->getState();
                if ($ticket_status == self::$commonParams['closedTicketStatus']) {
                    $errors[] = 'Тикет закрыт';
                }
            }
        } else {
            $errors[] = 'Не найден';
        }

        return $errors;
    }

    public static function addToTicket($postData)
    {
        /**
         * Добавляет ответ в заявку
         */
        try {
            function_exists('json_encode') or die('JSON support required');
            self::createUser($postData['email'], $postData['name']); // создет пользоваетля если его не было до этого
            if (!empty($postData['attachments'])) {
                $fileId = self::attacmentsUpload($postData['attachments']);
            }

            $errors = self::validateParams($postData);
            if (empty($errors)) {
                $user =  ApiOrm::getUserByName($postData['name']);
                $data['id'] =  Ticket::getIdByNumber((int)$postData['ticketNumber']);
                $data['userId'] =  $user['id'];
                $data['poster'] = $postData['name'];
                $data['ip_address'] = $postData['ip'];
                $data['type'] = self::$commonParams['ownerMessageType']; //'M' - Message owne
                $data['flags'] = ThreadEntry::FLAG_BALANCED; // HTML does not need to be balanced on ::display()
                $data['body'] = $postData['message'];
                $data['html'] = self::$commonParams['htmlFormat']; //html
                $data['created'] = self::$commonParams['sqlFunctionNow']; //SqlFunction NOW();
                $data['updated'] = self::$commonParams['sqlFunctionNow'];
                if (!ApiOrm::addAnsver($data['id'], 0, $data['userId'], $data['type'], $data['flags'], $data['poster'], $data['body'], $data['html'], $data['ip_address'], $data['created'], $data['updated'])) {
                    $message = ['status' => 'failed', 'message' => 'SQL query not executed'];
                }
                if (!empty($postData['attachments'])) {
                    ApiOrm::addAttachment($fileId);
                }

                $message = ['status' => '201', 'message' => 'Reply posted succesfully'];
            } else {
                $message = ['status' => '400', 'errors' => $errors];
            }
            return json_encode($message);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $result = array('status' => '400', 'status_msg' => $msg);
            return json_encode($result);
        }
    }
    public static function createTicket($data)
    {

        /**
         * Тут решено не изобретать велосипед и отдать встроенной апишке (работает только создание тикета)
         */
        $config = array_pop($data);
        $validateData = $data;
        if (!empty($data['attachments']))
            $validateData['attachments'] = array($data['attachments']);

        function_exists('curl_version') or die('CURL support required');
        function_exists('json_encode') or die('JSON support required');
        self::createUser($validateData['email'], $validateData['name']); // создет пользоваетля если его не было до этого
        #set timeout
        set_time_limit(40);
        #curl post
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $config['old_api']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validateData));
        curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket API Client v1.7');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:', 'X-API-Key: ' . $config['server_key']));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // эти две строчик ниже для подключение к https. Отключает проверку SSL сертификата
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code != 201)
            die('Unable to create ticket: ' . $result);
        $ticket_id = (int) $result;
        return json_encode(array(
            'status' => 201,
            'ticket' =>  $ticket_id
        ));
    }
    public static function checkTicketOwner($data)
    {
        /**
         * Проверяет наличие у пользователя билета
         * 
         * @param $data['username'] - имя проверяемого пользователя
         * @param $data['ticketNumber'] - проверяемый билет
         * 
         * @return -1 или id билета
         */
        $ticket = self::getTicketByNumber((int)$data['ticketNumber']);
        if (!$ticket) {
            return -1;
        }
        if ($data['username'] != self::getTicketOwner($ticket)) {
            return -1;
        }
        return Ticket::getIdByNumber((int)$data['ticketNumber']);
    }


    public static  function getMessageStory($data)
    {
        /**
         * Получает цепочку сообщений в массив
         * @param $data['username'] - имя пользователя запросившего историю
         * @param $data['ticketNumber'] номер запрашиваемого тикета 
         * @param $data['ignored'] - флаг, осуществалять ли проверку или нет если 1, то проверка на принадлежность пользователя к ти кету осуществляется
         * 
         * @return 0 или оформленный массив сообщений
         */
        $config = array_pop($data); // извлекаем конфиги
        $check = Api2Controller::checkTicketOwner($data);
        if ($check == -1) {
            return json_encode(array('status' => 400, 'message' => 'Пользователь с такой заявкой не найден'));
        }
        $ticketId = Ticket::getIdByNumber((int)$data['ticketNumber']);
        $result = ApiOrm::getMessageStory($ticketId);
        $message = array();
        $i = 0;
        foreach ($result as $element) {
            if (is_numeric($element['file_id'])) {
                $attacment = "\nВложение: https://". $_SERVER['SERVER_NAME'] . '/api2/' . self::getLoadUrl((string)$element["file_id"], $config);
            } else {
                $attacment = '';
            }
            $message[$i]['poster'] = $element['name'];
            $message[$i]['created'] = $element['created'];
            $message[$i]['body'] = $element['body'] . $attacment;
            $i += 1;
        }
        return json_encode(array('status' => 200, 'messages' => $message));
    }
    public static function getTopicList()
    {
        /**
         * Функция получает из бд все топики
         */
        $r = ApiOrm::getTopic();
        $result = array();
        foreach ($r as $row) {
            $result[$row['topic']] = $row['topic_id'];
        }
        return json_encode(array('status' => 200, 'topics' => $result));
    }


    private static function attacmentsUpload($data)
    {
        /**
         * Функция берет из json запроса $data['attacments']
         * Преобразует в данные для отправки и отправляет на сервер
         */
        $errors = array();
        if (!is_array($data)) {
            $errors[] = 'параметр это массив ["attacments"]';
        }
        // Взято по-моему из самого osticket
        foreach ($data as &$value) {
            $newData = reset($value);
            $contents = Format::parseRfc2397($newData, 'utf-8', false);
            $value = array(
                'data' => $contents['data'],
                'type' => $contents['type'],
                'name' => key($value),
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

    static function getLoadUrl($fileId, $api_config)
    {
        /**
         * Генерирует ссылку на скачивание/просмотр изображения изображения
         * ссылка должа быть на существующий файл get_picture.php который обрабатывает гет запрос 
         * @param $fileId - Тип int id записи из таблицы FILE_TABLE
         * @return url для скачивания изображения
         * 
         * (ЕСТЬ СТРАННЫЙ БАГ, ЧТО ПРИ ОТКРЫТИИ НЕКОТОРЫМИ БРАУЗЕРАМИ
         * ССЫЛКА СТАНОВИТСЯ БИТОЙ!)
         */

        $encryptedId = @openssl_encrypt($fileId, $api_config['encrypt_method'], $api_config['encrypt_key']);
        $id = openssl_decrypt($encryptedId, $api_config['encrypt_method'], $api_config['encrypt_key']); // расшифровка
        $url = $api_config['picture_url'] . '?id=' . urlencode($encryptedId);
        return $url;
    }
}
