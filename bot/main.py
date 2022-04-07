import re
from typing import Text
from flask import Flask, request
from requests.models import Response
from telebot.types import File, Message, KeyboardButton, ReplyKeyboardMarkup, InlineKeyboardButton, InlineKeyboardMarkup, CallbackQuery
import sys
import telebot  # pyTelegrambotapi
import time
import base64
import osticketbot as tgb
import config  # Хранит конфигурационные перменные
import bd

app = Flask(__name__)
bot = telebot.TeleBot(config.token)
osBot = tgb.OsTelegramBot(config.token, config.os_url, config.os_key)
if config.message_log:
    bd_work = bd.BdWork(config.bd_config)  # Для работы с бд
else:
    bd_work = None


def get_photo(photo_array):
    """
    Args:
        photo_array - list содаржащий все версии фотографии -1 элемент
        это оригинальный размер

    Returns:
        подходящий для отпарвки API словать
    """
    file_id = photo_array[-1].file_id
    file_info = bot.get_file(file_id)
    downloaded_file = bot.download_file(file_info.file_path)
    mimie_type = str(file_info.file_path.split('.')[-1])
    data = 'data:image/' + mimie_type + ';base64,' + \
        base64.b64encode(downloaded_file).decode("utf-8")
    return {file_id + '.' + mimie_type: data}


def add_to_log(bd_work: bd.BdWork or None, message: Message, response: str):
    """
    Данная функция логирует сообщения, если класс работы с бд был 
    все-таки создан
    Args:
        bd_work: сам класс или None
        message (Message): объект сообщения пользователя
        respose (str): Ответ который сервер отправит
    """
    if bd_work == None:
        return
    param = {
        'message_text': message.text,
        'user_id': message.chat.id,
        'response': response
    }
    bd_work.addToLogTable(param)


def build_menu(button_list_str: list[str], num_row=2):
    button_list = [KeyboardButton(ss) for ss in button_list_str]
    menu = [button_list[i:i + num_row]
            for i in range(0, len(button_list), num_row)]
    keyboard = ReplyKeyboardMarkup()
    for line in menu:
        keyboard.row(*line)
    return keyboard


def bild_inline_menu(button_dict: dict, name: str = 'ilbtn'):
    """
    Args:
        button_dict (dict): Словарь содержащий пары 
        "название кнопки":"значение ф-ции"
        name (str, optional): Defaults to 'ilbtn'. Префикс ф-ции

    Returns:
       Готовая к использованию клавиатура сообщения
    """
    inline_kb = InlineKeyboardMarkup()
    for key, value in button_dict.items():
        inline_kb.add(InlineKeyboardButton(
            key, callback_data=value))
    return inline_kb


keyboard1 = build_menu(config.keyboard_menu, 2)
keyboard_sections = bild_inline_menu(config.sections_ticket)


@app.route('/', methods=["POST"])
def webhook():
    bot.process_new_updates(
        [telebot.types.Update.de_json(request.stream.read().decode("utf-8"))]
    )
    return "ok"


@app.route('/ansver/', methods=["POST"])
def new_ansver():
    """
    Будет принимать обновления заявки в параметрах передавать 
    chat_id: id пользователя
    ticket_number: Номер нового билета
    message: Новое сообщение
    """
    post = request.get_json()
    print(post)
    if config.server_key != post["key"]:
        print("Ключ не подходит!!!")
        return "Bad Key!"
    user = post['user']
    user = re.search(r"\d+", user)
    bot.send_message(user.group(), osBot.htmlClear(post["text"]))
    return "ok"


"""
##################################################################################################################
***** ТУТ УЖЕ СОБЫТИЯ БОТА *****
##################################################################################################################
"""


@bot.callback_query_handler(func=lambda call: True)
def callback_inline(call):
    code = call.data
    if code.isdigit():
        response = 'Введите текст проблемы'
        bot.send_message(call.message.chat.id, response)
        add_to_log(bd_work, call.message, response)
        bot.register_next_step_handler(call.message, ask_body_ticket, code)


@bot.message_handler(commands=['help'])
def help_command(message: Message):
    """
    Вывод справки о командах
    Args:
        message (Message):
    """
    param = message.text.split()
    if len(param) > 1:
        response = osBot.getHelpText(param[1])
        bot.send_message(message.chat.id, response,  reply_markup=None)
    else:
        response = osBot.getHelpText()
        bot.send_message(message.chat.id, response, reply_markup=None)
    add_to_log(bd_work, message, response)


@bot.message_handler(commands=['create'])
def create_command(message: Message):
    response = 'Выберите категорию:'
    bot.send_message(message.chat.id, response, reply_markup=keyboard_sections)
    add_to_log(bd_work, message, response)


@bot.message_handler(commands=['add'])
def add_command(message: Message):
    response = 'Введите номер вашего билета'
    bot.send_message(message.chat.id, response)
    add_to_log(bd_work, message, response)
    bot.register_next_step_handler(message, ask_ticket_to_add)


@bot.message_handler(commands=['history'])
def history_command(message: Message):
    response = 'Введите номер запрашиваемого билета'
    bot.send_message(message.chat.id, response)
    add_to_log(bd_work, message, response)
    bot.register_next_step_handler(message, ask_ticket_history)


@bot.message_handler(content_types=['text', 'document', 'photo'])
def all_text_command(message: Message):
    """
    если нет сообщения, то поле message.text = None
    если нет документа, то поле message.document = None
    если нет фотографии, то поле message.photo = None
    если нет писания к вложению, то поле message.caption = None
    """
    response = 'Воспользуйтесь клавиатурой для выбора нужного'
    bot.send_message(
        message.chat.id, response, reply_markup=keyboard1)
    add_to_log(bd_work, message, response)


def ask_ticket_history(message: Message):
    number = int(message.text)
    result = osBot.getStoryMessage(message.chat.username, number)
    if result == '0':
        response = 'Зявка с таким номером не существует или вы не являетесь ее владельцем'
        bot.send_message(
            message.chat.id, response)
        add_to_log(bd_work, message, response)
    else:
        response = result
        bot.send_message(message.chat.id, result)
        add_to_log(bd_work, message, response)


def ask_ticket_to_add(message: Message):
    result = osBot.getTicketOwner(message.chat.username, message.text)
    if result == '-1':
        response = 'Зявка с таким номером не существует или вы не являетесь ее владельцем'
        bot.send_message(
            message.chat.id, response)
        add_to_log(bd_work, message, response)
    else:
        response = 'Введите ответ'
        bot.send_message(message.chat.id, response)
        add_to_log(bd_work, message, response)
        bot.register_next_step_handler(
            message, add_ticket_ansver,  message.text)


def add_ticket_ansver(message: Message, ticket_number):
    user_data = {'username': message.chat.username,
                 'id': 'id' + str(message.chat.id) + '@host.com'}
    if not message.photo is None:
        attacment = get_photo(message.photo)
        result = osBot.addToTicket(
            ticket_number, message.caption, user_data, attachments=attacment)
    else:
        result = osBot.addToTicket(ticket_number, message.text, user_data)
    if result['status'] == 'success':
        response = 'Ответ успешно добавлен'
        bot.send_message(message.chat.id, response)
        add_to_log(bd_work, message, response)
    else:
        response = 'Ошибка добавления ответа'
        bot.send_message(message.chat.id, response)
        add_to_log(bd_work, message, response)


def ask_body_ticket(message: Message, category='0'):
    """
    Args:
        message (Message): сообщение которое будет добавлено
    """
    user_data = {'username': message.chat.username,
                 'id': 'id' + str(message.chat.id) + '@host.com'}
    if not message.photo is None:
        attacment = get_photo(message.photo)
        result = osBot.createTicket(
            user_data,  message.caption, attacment, category=category)
    else:
        result = osBot.createTicket(
            user_data,  message.text, category=category)
    bot.send_message(message.chat.id, result)
    add_to_log(bd_work, message, result)


if(__name__ == '__main__'):
    bild_inline_menu(config.sections_ticket)
    if sys.argv[1] == 'webhook':
        print('webhook')
        bot.set_webhook(url=config.webhook_url)
        time.sleep(1)  # для фикса ошибки с большим кол-вом реквестов
    app.run(debug=True)
