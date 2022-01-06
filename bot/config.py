token = '1995864519:AAHR1ceeWOgoPAHWUjpwe37lmDGkLPTLLqE'  # Телеграм бот токен

webhook_url = 'https://04ee-87-117-61-38.ngrok.io' # Ссылка на ngrook

os_url = 'https://osticket.local/my_api/myapi.php'

os_key = '68AFA8405E8B569A1E8441C841182CFD' # Для доступа к osticketAPI

server_key = "KEY-12345"  # Ключ, для flask (для безопасности)
keyboard_menu = ['/help', '/create', '/add', '/history']


keyboard_seqtion = ['Access Issue', 'Feedback', 'General Inquiry',
                    'Report a Problem', 'Второй тип заявок', 'Тип_Заявок_1']
sections_ticket = {
    'Access Issue': '11',
    'Feedback': '2',
    'General Inquiry': '1',
    'Report a Problem': '10',
    'Второй тип заявок': '13',
    'Тип_Заявок_1': '12',
}

message_log = True  # если True то говорит логгировать все сообщения в базе

bd_config = {
    'host': 'localhost',
    'username': 'telegrambot_user',
    'password': 'telegrambot_user1',
    'bd_name': 'telegrambot_user'
}

# Текст для команды /help