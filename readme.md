# Программное обеспечение для работы Osticket с telegram-ботом
## Структура 
* **osapi** -- директория которая содержит API для работы бота с системой остикет. Позволяет не только создавать тикеты (заявки), но и отвечать в билет, а также получать информацию о заявках.
* **bot** -- директория содержит код бота который будет взаимодействовать с API телеграма и API osticket.
* **ticket_plugin** -- директория содержит плагин для osticket который будет отправлять уведомления о новых заявках боту.

## Установка
Для установки плагина потребуется мастер установки плагинов. Как пользоваться утилитой и устанвока по [ссылке](https://github.com/osTicket/osTicket-plugins).
При верной устанвоке в панеле администратора при нажатии на кнопку **" :heavy_plus_sign: добавить новый плагин"** должен появится плагин с названием **"Telegram bot tech. support"**      

Для устанвоки API Требуется создать в проекте папку доступную из веба (напимер */upload/my_api*) и переместить туда все файлы папки **osapi**. Далее необходимо настроить файл **config.php**. Там необходимо настроить ключ шифрования и ввести ключ шифрования     

Для работы c телеграммом требуется https соеденине *(другое API телеграма не воспринимает)*. Также как и для API osticket необходимо настроить файл **config.py**. В нем прописываются такие параметры как webhook для telegram *(webhook_url)* и, ссылка на API *()* и ключ *()*, также тут задается ключ для приходящих на фласк запросов *()*, и кнопки клавиатур

## Работа с ботом
