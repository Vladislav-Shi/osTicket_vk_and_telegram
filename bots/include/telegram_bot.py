import base64
import telebot
import telebot.types as tb_type
from telebot.types import Message
from bots.include.osAPI import osAPI
import bots.include.config as config


class TelegramBot(telebot.TeleBot):
    '''

    '''

    def __init__(self,
                 token: str,
                 os_api: osAPI,
                 parse_mode=None,
                 threaded=True,
                 skip_pending=False,
                 num_threads=2,
                 next_step_backend=None,
                 reply_backend=None,
                 exception_handler=None,
                 last_update_id=0,
                 suppress_middleware_excepions=False,
                 state_storage=...):
        self.os_api = os_api
        super().__init__(token, parse_mode, threaded, skip_pending, num_threads, next_step_backend,
                         reply_backend, exception_handler, last_update_id, suppress_middleware_excepions, state_storage)

    def set_token(self, token):
        '''
        Переопределяет токен доступа
        '''
        self.token = token

    @staticmethod
    def build_menu(button_list_str: list[str], num_row=2):
        '''
        Строит меню в клавиатуре
        '''
        button_list = [tb_type.KeyboardButton(ss) for ss in button_list_str]
        menu = [button_list[i:i + num_row]
                for i in range(0, len(button_list), num_row)]
        keyboard = tb_type.ReplyKeyboardMarkup()
        for line in menu:
            keyboard.row(*line)
        return keyboard

    @staticmethod
    def bild_inline_menu(button_dict: dict, name: str = 'ilbtn:'):
        '''
        Строит клавиатуру привязанную к сообщению
            button_dict (dict): Словарь содержащий пары 
            'название кнопки':'значение ф-ции'
            name: Defaults to 'ilbtn'. Префикс ф-ции
        Returns:
        Готовая к использованию клавиатура сообщения
        '''
        inline_kb = tb_type.InlineKeyboardMarkup()
        for key, value in button_dict.items():
            inline_kb.add(tb_type.InlineKeyboardButton(
                key, callback_data=name + str(value)))
        return inline_kb

    def get_photo(self, photo_array: list) -> dict:
        '''
            photo_array - list содаржащий все версии фотографии -1 элемент
            это оригинальный размер
        Returns:
            подходящий для отпарвки API словать
        '''
        file_id = photo_array[-1].file_id
        file_info = self.get_file(file_id)
        downloaded_file = self.download_file(file_info.file_path)
        mimie_type = str(file_info.file_path.split('.')[-1])
        data = 'data:image/' + mimie_type + ';base64,' + \
            base64.b64encode(downloaded_file).decode('utf-8')
        return {file_id + '.' + mimie_type: data}


# Костыль чтобы не было создания кучи заявок по одному сообщению
query = 0

# переменная бота
# treaded исправить на True если у вас хост поддерживате потоки
telegram_bot = TelegramBot('token',
                           os_api=osAPI(config.os_url, config.os_token),
                           threaded=False  # Если многопоточность поддерживается, поменять на True
                           )
'''
##################################################################################################################
***** ТУТ УЖЕ СОБЫТИЯ БОТА *****
##################################################################################################################
'''


@telegram_bot.message_handler(commands=['test'])
def test(message: Message):
    '''
    Тест бота. Если все как надо отправит сообщение test
    '''
    telegram_bot.send_message(message.chat.id, 'test',  reply_markup=None)


def create_command(message: Message):
    '''
    Сообщение которое выведится при нажатии созать заявку
    '''
    response = 'Выберите категорию:'
    section = telegram_bot.os_api.get_category()
    # name = '' для того чтобы сразу шло число
    return response, TelegramBot.bild_inline_menu(section['topics'], name='')


def add_command(message: Message):
    '''
    Сообщение которое выведится для пользователя при нажатии на добавление ответа
    '''
    response = 'Введите номер вашего билета'
    keyboard = telegram_bot.os_api.get_user_ticekts(message.chat.username)
    if 'tickets' not in keyboard:
        return 'Заявок не найдено! Самое время задать вопрос', ''
    return response,  TelegramBot.bild_inline_menu(keyboard['tickets'], name='add:')


def history_command(message: Message):
    '''
    Сообщение которые выведется при выборе кнопки показать историю
    '''
    response = 'Выберите номер запрашиваемого билета'
    keyboard = telegram_bot.os_api.get_user_ticekts(message.chat.username)
    if 'tickets' not in keyboard:
        return 'Заявок не найдено! Самое время задать вопрос', ''
    return response,  TelegramBot.bild_inline_menu(keyboard['tickets'], name='history:')


def help_command(message: Message):
    '''
    Сообщение которые выведется при выборе кнопки показать справку
    '''
    response = config.tg_help_text
    return response, None


def ask_ticket_history(number: str, message: Message):
    '''
    по выбранному номеру выдает собственно историю заявки
    '''
    result = telegram_bot.os_api.get_story_messages(
        number, message.chat.username)
    if result['status'] == 200:
        response = 'Заявка ' + str(number) +'\n\n'
        for i in result['messages']:
            response += 'Автор:' + \
                str(i['poster']) + ' Дата: ' + str(i['created']) + '\n'
            response += 'Сообщение: ' + str(i['body']) + '\n\n'
        telegram_bot.send_message(
            message.chat.id, response)
    else:
        response = 'Заявки не слуществует или принадлежит не вам!'
        telegram_bot.send_message(message.chat.id, response)


def ask_body_ticket(message: Message, category='0'):
    '''
    Функция которая просит ввести тело вопроса
    message: обьект сообщения пользователя
    сategory='0': категория вопроса
    '''
    attacment = {}
    u_message = message.text
    # костыль против спама
    global query
    if query > 1:
        query -= 1
        return 'ok'
    if not message.photo is None:
        attacment = telegram_bot.get_photo(message.photo)
        u_message = message.caption
    result = telegram_bot.os_api.create_ticket(message=u_message,
                                               user_id='id' +
                                               str(message.chat.id) +
                                               '@host.com',
                                               username=message.chat.username,
                                               attachments=attacment,
                                               category=category)
    if result['status'] == 201:
        text = 'Успешно добавлена заявка под номером ' + str(result['ticket'])
    else:
        text = 'Ошибка добавления заявки!'
    telegram_bot.send_message(message.chat.id, text)
    query = 0


def add_ticket_ansver(message: Message, ticket_number):
    attacment = {}
    u_message = message.text
    # костыль против спама
    global query
    if query > 1:
        query -= 1
        return 'ok'
    if not message.photo is None:
        attacment = telegram_bot.get_photo(message.photo)
        u_message = message.caption
    result = telegram_bot.os_api.add_ansver(
        ticket_number,
        u_message,
        message.chat.username,
        'id' + str(message.chat.id) + '@host.com',
        attacment
    )
    if result['status'] == '201':
        response = 'Ответ успешно добавлен'
        telegram_bot.send_message(message.chat.id, response)
    else:
        response = 'Ошибка добавления ответа'
        telegram_bot.send_message(message.chat.id, response)
    query = 0


# клавиатуры действий доступных пользоваетлю
#
tg_action_dict = {
    'Создать заявку': 1,
    'Добавить ответ в заявку': 2,
    'Посмотреть историю заявки': 3,
    'Справка': 4
}
# второй словарь нужен так как в обработчик (call.data) нельзя передать функцию (но это не точно)
tg_action_function = {
    '1': create_command,
    '2': add_command,
    '3': history_command,
    '4': help_command
}

tg_action_keybord = TelegramBot.bild_inline_menu(
    tg_action_dict, name='action:')


@telegram_bot.callback_query_handler(func=lambda call: True)
def callback_inline(call):
    '''
    Функция обрабатывает нажатия inline клавиатуры в 
    code.data -- переданная строка с префиксом и значением
    code.message -- данные пользователя
    '''
    global query
    code = call.data
    if code.isdigit():
        response = 'Введите текст проблемы'
        # костыль против спама
        query += 1
        telegram_bot.send_message(call.message.chat.id, response)
        telegram_bot.register_next_step_handler(
            call.message, ask_body_ticket, code)
    # парсим сообщение
    message = call.data.split(':')
    if message[0] == 'action':
        response, keyboard = tg_action_function[message[1]](call.message)
        telegram_bot.send_message(
            call.message.chat.id, response, reply_markup=keyboard)
    if message[0] == 'history':
        ask_ticket_history(message[1], call.message)
    if message[0] == 'add':
        # костыль против спама
        query += 1
        response = "Введите свой ответ"
        telegram_bot.send_message(
            call.message.chat.id, response, reply_markup=None)
        telegram_bot.register_next_step_handler(
            call.message, add_ticket_ansver,  message[1])


@telegram_bot.message_handler(content_types=['text', 'document', 'photo'])
def all_text_command(message: Message):
    '''
    Срабатывает на все остальыне сообщения

    если нет сообщения, то поле message.text = None
    если нет документа, то поле message.document = None
    если нет фотографии, то поле message.photo = None
    если нет писания к вложению, то поле message.caption = None
    '''
    response = 'Воспользуйтесь клавиатурой для выбора нужного действия'

    telegram_bot.send_message(
        message.chat.id, response, reply_markup=tg_action_keybord)
