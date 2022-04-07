<?php
include_once 'config.php';
include_once 'class.myapi.php';
/**
 * API со стороны sticket для телеграм бота
 * и по сути API для API osticket
 * API принимает пост запросы содержащие имя и параметры функции и обрабатывает их
 * $_POST['function'] - название функции которая может быть вызвана
 * $_POST['arg'] - ассоциативный массивы аргументов к ней
 */
$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$APIobject = new MyAPI($apiConfig['os_url'], $apiConfig['os_key'], $apiConfig);
if (empty($data['function'])) {
    die('Пост запрос пуст или составлен не правильно');
}
$APIobject = new MyAPI($apiConfig['os_url'], $apiConfig['os_key'], $apiConfig);
if ($data['key'] != '68AFA8405E8B569A1E8441C841182CFD') {
    die('Неверный ключ досутпа к API');
}
echo $APIobject->callMethod($data);
