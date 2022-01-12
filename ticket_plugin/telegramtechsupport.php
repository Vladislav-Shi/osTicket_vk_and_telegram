<?php

require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.ticket.php');
require_once(INCLUDE_DIR . 'class.osticket.php');
require_once(INCLUDE_DIR . 'class.config.php');
require_once(INCLUDE_DIR . 'class.format.php');
require_once('config.php');


class TelegramTechSupport extends Plugin
{
    var $config_class = 'TelegramTechSupportConfig';
    function bootstrap()
    {
        Signal::connect('threadentry.created', array($this, 'onTicketUpdated'));
    }
    function onTicketUpdated(ThreadEntry $entry)
    {
        /**
         * Данная функция инициирует отправку сообщения о новом сообщении в билете пользователя
         */
        $key_url = $this->getConfig()->get('bot_url_key');
        $url = $this->getConfig()->get('bot_url');
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("cfg not instanceof OsticketConfig. TelegramTechSupport");
            return;
        }
        $ticket = $this->getTicket($entry);
        if (!$ticket instanceof Ticket) {
            return;
        }
        // Если тот же пользователь ответил, то нет смысла отправлять
        if ($ticket->getOwnerId() == $entry->getUserId()) {
            return;
        }
        // берем с конфига ссылку и ключ
        preg_match("/^id\d*@host/", $ticket->getEmail(), $user_telegram_id);
        // почты полученные из телеги хранятся в виде "id<номер_пользователя>@host.com"
        if (empty($user_telegram_id)) {
            return;
        }
        $text = "*UPD:*Получено новое сообщение!\n\nТicket: " . $ticket->getNumber()
            . "\nText: " . $entry->getBody();
        $post = array("key" => $key_url, "user" => $user_telegram_id[0], "text" => $text);
        $post = json_encode($post, JSON_UNESCAPED_UNICODE);
        file_put_contents("volsu_log.txt", "Second\n" . $post);
        $this->sendToBot($url, $post);
    }
    function getTicket(ThreadEntry $entry)
    {
        $ticket_id = Thread::objects()->filter(['id' => $entry->getThreadId()])->values_flat('object_id')->first()[0];
        return Ticket::lookup(array('ticket_id' => $ticket_id));
    }

    function sendToBot($url, $post)
    {
        /**
         * отправляет боту POST запрос
         */
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $temp = curl_exec($ch);
        $ee = curl_getinfo($ch);
        curl_close($ch);
        file_put_contents("volsu_log.txt", print_r($ee, true) . "\n\n" . print_r($temp, true));
        return json_decode($temp, true);
    }
}
