from re import A
from typing import Text
from flask import Flask, request
from telebot.types import File, Message, KeyboardButton, ReplyKeyboardMarkup
import osticketbot as tgb
import telebot  # pyTelegrambotapi
import time
import base64
import config  # Хранит конфигурационные перменные

app = Flask(__name__)
bot = telebot.TeleBot(config.token)
osBot = tgb.OsTelegramBot(config.token, config.os_url,
                          config.os_key)
bot.set_webhook(url=config.webhook_url)
time.sleep(1)  # для фикса ошибки с большим кол-вом реквестов


def build_menu(button_list_str: list[str], num_row=2):
    button_list = [KeyboardButton(ss) for ss in button_list_str]
    menu = [button_list[i:i + num_row]
            for i in range(0, len(button_list), num_row)]
    print(menu)
    keyboard = ReplyKeyboardMarkup()
    for line in menu:
        keyboard.row(*line)
    return keyboard


keyboard1 = build_menu(config.keyboard_menu, 2)
keyboard2 = build_menu(config.keyboard_seqtion, 2)


@app.route('/', methods=["POST"])
def webhook():
    bot.process_new_updates(
        [telebot.types.Update.de_json(request.stream.read().decode("utf-8"))]
    )
    return "ok"


@bot.message_handler(commands=['help'])
def help_command(message: Message):
    """
    Вывод справки о командах
    Args:
        message (Message):
    """
    param = message.text.split()
    if len(param) > 1:
        bot.send_message(message.chat.id, osBot.getHelpText(
            param[1]),  reply_markup=None)
    else:
        bot.send_message(message.chat.id, osBot.getHelpText(),
                         reply_markup=None)


@bot.message_handler(commands=['create'])
def create_command(message: Message):
    bot.send_message(message.chat.id, "Введите текст проблемы")
    bot.register_next_step_handler(message, ask_body_ticket)


@bot.message_handler(commands=['add'])
def add_command(message: Message):
    bot.send_message(message.chat.id, "Введите номер вашего билета")
    bot.register_next_step_handler(message, ask_ticket_to_add)


@bot.message_handler(commands=['history'])
def history_command(message: Message):
    bot.send_message(message.chat.id, 'Введите номер запрашиваемого билета')
    bot.register_next_step_handler(message, ask_ticket_history)


@bot.message_handler(content_types=['text', 'document', 'photo'])
def all_text_command(message: Message):
    """
    если нет сообщения, то поле message.text = None
    если нет документа, то поле message.document = None
    если нет фотографии, то поле message.photo = None
    если нет писания к вложению, то поле message.caption = None
    """
    print('text: ', message.text)
    print('document: ', message.document)
    print('caption: ', message.caption)
    print('photo: ', message.photo)
    # if not message.photo is None:
    # print(getPhoto(message.photo))
    bot.send_message(
        message.chat.id, 'Воспользуйтесь клавиатурой для выбора нужного', reply_markup=keyboard1)


def ask_ticket_history(message: Message):
    number = int(message.text)
    print('number', number)
    result = osBot.getStoryMessage(message.chat.username, number)
    if result == '0':
        bot.send_message(
            message.chat.id, 'Зявка с таким номером не существует или вы не являетесь ее владельцем')
    else:
        bot.send_message(message.chat.id, result)


def ask_ticket_to_add(message: Message):
    response = osBot.getTicketOwner(message.chat.username, message.text)
    print('response: ', response)
    if response == '-1':
        bot.send_message(
            message.chat.id, 'Зявка с таким номером не существует или вы не являетесь ее владельцем')
    else:
        bot.send_message(message.chat.id, 'Введите ответ')
        bot.register_next_step_handler(
            message, add_ticket_ansver,  message.text)


def add_ticket_ansver(message: Message, ticket_number):
    user_data = {'username': message.chat.username,
                 'id': 'id' + str(message.chat.id) + '@host.com'}
    if not message.photo is None:
        attacment = getPhoto(message.photo)
        result = osBot.addToTicket(
            ticket_number, message.caption, user_data, attachments=attacment)
    else:
        result = osBot.addToTicket(ticket_number, message.text, user_data)
    print('result', result)
    if result['status'] == 'success':
        bot.send_message(message.chat.id, 'Ответ успешно добавлен')
    else:
        bot.send_message(message.chat.id, 'Ошибка добавления ответа')


def ask_body_ticket(message: Message):
    """[summary]

    Args:
        message (Message): сообщение которое будет добавлено
    """
    user_data = {'username': message.chat.username,
                 'id': 'id' + str(message.chat.id) + '@host.com'}
    if not message.photo is None:
        attacment = getPhoto(message.photo)
        result = osBot.createTicket(user_data,  message.caption, attacment)
    else:
        result = osBot.createTicket(user_data,  message.text)
    bot.send_message(message.chat.id, result)


def getPhoto(photo_array):
    """[summary]

    Args:
        photo_array - list содаржащий все версии фотографии -1 элемент это оригинальный размер

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


if(__name__ == '__main__'):
    app.run(debug=True)
