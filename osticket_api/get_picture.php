<?php
include_once 'config.php';
include_once 'class.api.php';
/**
 * Файл который нужно открывать с гет запросом
 * гет должен содержать GET['id']
 */
if(empty($_GET['id']))
{
    die('пустой запрос');
}
$id = @openssl_decrypt($_GET['id'], $api_config['encrypt_method'], $api_config['encrypt_key']);
if(!is_numeric($id))
{
    die('Ошибка в создании ключа');
}
Api2Controller::getPicture((int)$id, $api_config);