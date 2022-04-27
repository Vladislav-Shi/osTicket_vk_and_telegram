import re
from flask import Flask, request
from telebot.types import Update
import bots.include.config as config
import bots.include.telegram_bot as tg_bot
import bots.include.vk_bot as vk_bot


WEBHOOK_SSL_CERT = '/etc/nginx/ssl/webhook_cert.pem'
app = Flask(__name__)
tg_bot.telegram_bot.set_token(config.tg_token)
tg_bot.telegram_bot.set_webhook(
    url=config.tg_webhook_url, certificate=open(WEBHOOK_SSL_CERT, 'r'))
print(tg_bot.telegram_bot.get_webhook_info())


@app.route('/tg/', methods=['POST'])
def tel_webhook():
    tg_bot.telegram_bot.process_new_updates(
        [Update.de_json(request.data.decode('utf-8'))]
    )
    return 'ok'  # аналогично как с телегой нужно отправить ok


@app.route('/vk/', methods=['POST'])
def vk_webhook():
    '''
    Сюда вк присылает все уведомления
    '''
    data = request.get_json()
    if data['type'] == 'message_new':
        print(data)
        vk_bot.vk_bot.create_answer(data['object']['message'])
    elif data['type'] == 'confirmation':
        return config.vk_webhook_key
    return 'ok'  # аналогично как с телегой нужно отправить ok


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
    tg_bot.telegram_bot.send_message(user.group(), post["text"], reply_markup=None)
    return "ok"


@app.route('/', methods=['GET'])
def index():
    return 'hello flask'


if __name__ == "__main__":
    app.run()
