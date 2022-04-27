import sys
sys.path.insert(0, 'YOUR PATH')
import include.osAPI as osApi


def create():
    '''
    Проверка создания заявки
    '''
    global os_bot
    print('create command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.create_ticket(
        message = 'Test message',
        user_id = 'id111111111@host.com',
        username = 'Test User',
        category=10
    ))


def add_ansver(number):
    '''
    проверка добавления ответа  в заявку
    @number -- номер заявки в какую добавляем
    '''
    global os_bot
    print('add_ansver command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.add_ansver(
        ticket_number=number,
        user_id='id111111111@host.com',
        username='Test User',
        message='add message'
    ))


def histori(number):
    '''
    выдает историю сообщений заявки
    @number -- номер выводимой заявки
    '''
    global os_bot
    print('get_story_messages command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.get_story_messages(
        ticket_number=number,
        username='John Doe2'
    ))


def category():
    '''
    Выводит список доступных категорий
    '''
    global os_bot
    print('get_category command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.get_category())


def add_ansver_with_photo():
    '''
    '''
    global os_bot
    print('create command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.create_ticket(
        'Test message',
        'id111111111@host.com',
        'Test User',
        attachments= ''
    ))


def create_with_photo():
    '''

    '''
    global os_bot
    print('create command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.create_ticket(
        'Test message',
        'id111111111@host.com',
        'Test User',
        category=10,
        attachments= ''
    ))


def open_ticket():
    '''
        список открытых заявок
    '''
    global os_bot
    print('get_user_ticekts command to:', os_bot.os_url)
    print('token:', os_bot.os_token)
    print(os_bot.get_user_ticekts('Test User'))


if __name__ == '__main__':
    token = 'key_key_key'  # Токен доступа
    url = 'https://osos/api2/api.php/'  # url до адпи
    os_bot = osApi.osAPI(os_token=token, os_url=url)

