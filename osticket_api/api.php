<?php
include_once 'config.php';
include_once 'class.api.php';

/**
 * 
 * API для работы с созданием\получением\редактированием заявок
 * API принимает POST запросы содержащие ключ и параметры функции и обрабатывает их
 * Адрес запроса будет представлять собой список один из методов класса API
 * $_POST['key'] - хранит ключ для доступа к функционалу
 * $_POST['args'] - ассациативный массив параметров к методу API
 */

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$url =  $_SERVER['REQUEST_URI'];
$urls = explode('/', $url); 
if ($data['key'] != $api_config['access_key']) {
    header('HTTP/1.0 403 Unauthorized');
    die('Неверный ключ досутпа к API');
}
$data['args']['param'] =  $api_config;
$responce = Api2Controller::callMethod(end($urls), $data['args']);
if(!$responce){
    header('HTTP/1.0 404 Not Found');
    die('Метод не найден');
}
echo $responce;