<?php
require_once INCLUDE_DIR . 'class.plugin.php';

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 * 
 *  Конфигурационный файл настроек плагина
 *  Плагину не нужно отправлять сообщения боту, плагину нужно отправлять сообщения 
 *  серверу где находится бот, чтобы тот сам инициировал отправку сообщений
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */

class TelegramTechSupportConfig extends PluginConfig
{
    function getOptions()
    {
        return array(
            'telegram' => new SectionBreakField(array(
                'label' => 'Telegram Bot for tech. support',
            )),
            'bot_url' => new TextboxField(array(
                'label' => 'Сслыка на сервер с ботом',
                'configuration' => array('size'=> 100, 'length' => 200),
                'default' => "http://localhost:5000/ansver/",
            )),
            'bot_url_key' => new TextboxField(array(
                'label' => 'Ключ для обработки сообщений ботом',
                'configuration' => array('size'=> 100, 'length' => 200),
            )),
        );
    }
}
