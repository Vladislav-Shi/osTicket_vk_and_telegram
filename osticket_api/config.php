<?php
$api_config = array(
    // для server_key создать доступ с ip сайта 
    'server_key' => 'KEY', // ключ дял доступа с этого сервера (нужен для связи со старым api)
    'old_api' => 'URL_TO_/api/ticket.php', // ссылка ко старому api
    'access_key' => 'KEY', // пост запрос к серверу должен содержать в параметрах этот ключ
    'encrypt_key' => 'b4ec3ca86e2h2h2h2h', // ключ шифрования для поиска картинок
    'encrypt_method' => 'AES-192-CBC', // и метод шифрования
    'picture_url' => 'URL_TO_/api2/get_picture.php' // ссылка на файл с выводом картинки
);