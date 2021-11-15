<?php
include_once 'config.php';
include_once 'class.myapi.php';
/**
 * Файл который нужно открывать с гет запросом
 * гет должен содержать GET['id']
 */
if(empty($_GET['id']))
{
    die('пустой запрос');
}
$id = @openssl_decrypt($_GET['id'], $apiConfig['encrypt_method'], $apiConfig['encrypt_key']);
if(!is_numeric($id))
{
    die('Ошибка в создании ключа');
}
MyAPI::getPicture((int)$id);