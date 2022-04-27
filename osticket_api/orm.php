<?php
require_once('../main.inc.php');
include_once INCLUDE_DIR . 'class.api.php';
include_once INCLUDE_DIR . 'class.ticket.php';
include_once INCLUDE_DIR . "class.json.php";

class ApiOrm
{
    /**
     * Данный класс реализует запросы в базу данных этого кастомного API
     */
    public static function getUserByName(string $name)
    {
        /**
         * Получает id пользователя по его email
         * @return id
         */
        $sql = sprintf(
            "SELECT id FROM %s WHERE name = %s LIMIT 1;",
            USER_TABLE,
            db_input($name)
        );
        $r = db_query($sql);
        $r = db_fetch_array($r, MYSQLI_ASSOC);
        return $r;
    }
    public static function addAnsver(
        int $id,
        $staffId,
        $userId,
        $type,
        $flags,
        $poster,
        $body,
        $html,
        $ip_address,
        $created,
        $updated
    ) {
        /**
         * Добавляет запись в таблоицу ответов пользователей
         */
        $sql = sprintf(
            "INSERT INTO %s (`id`, `pid`  ,`thread_id` ,`staff_id` ,`user_id` ,`type`
        ,`flags` ,`poster` ,`editor` ,`editor_type` ,`source` ,`title` ,`body` ,
        `format` ,`ip_address` ,`created` ,`updated`) VALUE
        (NULL, 0, %d, %d, %s, %s, %s, %s, NULL , NULL , 'API', '', %s,  %s, %s, NOW(), NOW());",
            THREAD_ENTRY_TABLE,
             db_input($id),
             db_input($staffId),
             db_input($userId),
             db_input($type),
             db_input($flags),
             db_input($poster),
             db_input($body),
             db_input($html),
             db_input($ip_address)
        );
        return db_query($sql);
    }

    public static function addAttachment($fileId)
    {
        /**
         * Добавляет в таблицу вложения
         * 
         */
        $sql =  sprintf(
            "INSERT INTO %s (id, object_id, type, file_id, name, inline, lang) VALUES 
            (NULL, LAST_INSERT_ID(), 'H', %d, NULL, 0, NULL)",
            ATTACHMENT_TABLE,
            db_input($fileId)
        );
        return db_query($sql);
    }
    public static function getMessageStory($ticketId)
    {
        /**
         * Получает список сообщений
         * @param ticketId - id заявки
         * @return array|NULL
         */
        $sql = sprintf(
            "SELECT th.*, u.name, u.default_email_id, att.file_id FROM %s AS th 
            LEFT JOIN %s AS u ON th.user_id = u.id 
            LEFT JOIN %s AS att ON th.id = att.object_id
            WHERE th.thread_id = %d ORDER BY th.id ASC; ",
            THREAD_ENTRY_TABLE,
            USER_TABLE,
            ATTACHMENT_TABLE,
            db_input($ticketId)
        );
        $r = db_query($sql);
        return db_assoc_array($r);
    }
    public static function getTopic()
    {
        /**
         * Возвращает список категорий запросов
         * @return array|NULL
         */
        $sql = sprintf("SELECT topic_id, topic FROM %s", TOPIC_TABLE);
        $r = db_query($sql);
        return db_assoc_array($r);
    }
    public static function createUser($name, $email)
    {
        /**
         * Создает пользователя по имени и почте
         */
        $sql = sprintf("CALL create_user(%s, %s)", db_input($email), db_input($name));
        $r = db_query($sql);
    }

    public static function getUserTicket($userId)
    {
        /**
         * Список заявок пользователя (и чуть чуть данных про них)
         */
        $sql = sprintf(
            "SELECT ticket_id, number, user_id, status_id, closed, created FROM %s 
        WHERE user_id = %d",
            TICKET_TABLE,
            db_input($userId)
        );
        $r = db_query($sql);
        return db_assoc_array($r);
    }

    public static function getUserTickets($user_id)
    {
        /**
         * Возвращает из бд все заявки пользователя по его id
         */
        $sql = sprintf(
            "SELECT `ticket_id`, `number`, `user_id`, `status_id`, `topic_id` FROM %s WHERE user_id = %d;",
            TICKET_TABLE,
            db_input($user_id)
        );
        $r = db_query($sql);
        return db_assoc_array($r);
    }

    static function createUserProcedure()
    {
        /**
         * Данный метод создает процедуру (так как с помощью нее более корректная
         * работа с пользвоателями)
         */
        $sql = "SHOW PROCEDURE status WHERE Name = 'create_user';";
        $r = db_query($sql);
        if (db_assoc_array($r) == 0) {
            $sql = sprintf("
                DELIMITER //
                CREATE PROCEDURE `create_user`(
                    IN email VARCHAR(30),
                    IN username VARCHAR(30)
                ) LANGUAGE SQL DETERMINISTIC SQL SECURITY DEFINER COMMENT 'Процедура для добавления пользователя с мылом и именем'
                BEGIN
                    DECLARE
                        mail_id INT ; DECLARE new_user_id INT ;
                    INSERT INTO %s (user_id, flags, address)
                VALUES(0, 0, email) ;
                SET
                    mail_id = LAST_INSERT_ID() ;
                INSERT INTO %s (
                    org_id,
                    default_email_id,
                STATUS
                    ,
                    NAME,
                    created,
                    updated
                )
                VALUES(
                    0,
                    mail_id,
                    0,
                    username,
                    NOW(), NOW()) ;
                SET
                    new_user_id = LAST_INSERT_ID() ;
                REPLACE
                INTO %s (id, user_id, flags, address)
                VALUES(mail_id, new_user_id, 0, email) ;
                END //
                ", USER_EMAIL_TABLE, USER_TABLE, USER_EMAIL_TABLE);
            db_query($sql);
        }
    }
}
