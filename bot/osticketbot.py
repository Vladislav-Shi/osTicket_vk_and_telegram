import re
import json
import requests
from telebot.types import PhotoSize


class OsTelegramBot:

    def __init__(self, botkey, osUrl, osToken) -> None:
        """
        Args:
            botkey ([string]): ключ для доступа к боту
            osUrl ([string]): ссылка на API остикет (ссылка типа .../api/tickets.json)
            osToken ([string]): ключ чтобы API osTicket могло обработаь запрос (брать в админке osTicket)
        """
        self.botkey = botkey
        self.osUrl = osUrl
        self.osToken = osToken
        # на stackowerflow было написано что нужно скомпилировать ОДИН раз
        self.CLEANR = re.compile('<.*?>')  # Для чистки от тегов

    def createTicket(self,
                     userData,
                     message,
                     attachments={},
                     userAgent='telegram-API/0.0.2',
                     category=0,
                     source='telegram-bot'):
        """[summary]
        отправляет curl апишке osticket на создание тикета

        Args:
            userData ([dict]): данные типа data['message']['chat']
            message ([type]): само тело тикета
            attachments (dict, optional): [description]. массив типа [{<имя файла> : <сообщение в кодировка base64>}]
            userAgent (str, optional): [description]. Defaults to 'telegram-API/0.0.1'.
            category: категория заявки, по умолчанию 0 (без категории)
            source: откуда получено

        Returns:
            Возвращает строку с id созданной заявки или 0 в случае неудачи
        """
        headers = {'X-API-Key': self.osToken,
                   'User-Agent': userAgent,
                   'content-type': 'application/json', }
        data = {
            'function': 'createTicket',
            'args':
            {
                'name': userData['username'],
                'email': userData['id'],
                'subject': userData['username'],
                'message': message,
                'topicId': category,
                'ip': '127.0.0.1',
                'attachments': attachments,
            },
            'key': self.osToken,
        }
        r = requests.post(self.osUrl, json=data, headers=headers, verify=False)
        print('r', r)
        if r.status_code == 201:
            return self.htmlClear(r.text)
        else:
            return self.htmlClear(r.text)

    def addToTicket(self,
                    ticketNumber,
                    message,
                    userData,
                    attachments={},
                    userAgent='telegram-API/0.0.1'):
        headers = {'X-API-Key': self.osToken,
                   'User-Agent': userAgent,
                   'content-type': 'application/json'}
        data = {
            'function': 'addToTicket',
            'args': {
                'ticketNumber': ticketNumber,
                'name': userData['username'],
                'email': userData['id'],
                'message': message,
                'ip': '127.0.0.1',
                'attachments': attachments
            },
            'key': self.osToken,
        }
        response = requests.post(
            self.osUrl, json=data, headers=headers, verify=False)
        print('addToTicket response.text: ', response.text)
        return json.loads(response.text)

    def getStoryMessage(self, username, ticketNumber):
        """
        Args:
            username (str): [description]
            ticketNumber (int): [description]

        Returns:
            текст ответа
        """
        data = {'function': 'getMessageStory',
                'args': {
                    'username': username,
                    'ticketNumber': ticketNumber,
                },
                'key': self.osToken, }
        response = requests.post(self.osUrl, json=data, verify=False)
        return self.htmlClear(response.text)

    def getHelpText(self, typeText='all'):
        """[summary]
        Возвращает все варианты подсказок в зависимости от контекста применения

        Args:
            typeText (str, optional): [description]. Defaults to 'all'.

        """
        if typeText == 'add':
            return '-add <id>\n<text>\n\n Команда добавляет ответ к уже созданной вами заявке\
                \nid - номер который вы получили когда создавали заявку \
                \ntext - это текст которым вы хотите ответить в диалоге заявки'
        elif typeText == 'create':
            return '-create\n<text>\n\nКоманда создает заявку \
            \ntext - текст создаваемой вами заявки'
        else:
            return 'Инструкция по использованию бота:\n-help\n* выводит инстуркцию по пользованию\
            \n\n -create <текст с новой строки>\n* Отправляет запрос на создание заявки с таким содержимым\
            \n\n -add <номер заявки> <текст с новой строки>\n* Добавляет ответ по номеру заявки'

    def getTicketOwner(self, username, ticket_number):
        data = {'function': 'checkTicketOwner',
                'args': {
                    'username': username,
                    'ticketNumber': ticket_number},
                'key': self.osToken}
        response = requests.post(self.osUrl, json=data, verify=False)
        return self.htmlClear(response.text)

    def getTopicFromApi(self):
        """
        ***********************************
        стоит использовать только для получения вывода в консоль

        Returns:
            Строку содержащую пару id:topit_name

        """
        data = {'function': 'getTopicList',
                'args': {},
                'key': self.osToken, }
        response = requests.post(self.osUrl, json=data, verify=False)
        return self.htmlClear(response.text)

    def htmlClear(self, text: str):
        """
        Очищает переданный в параметре текст от всех тегов
        Args:
            text (str): Текст который требуется очистить

        """
        cleantext = re.sub(self.CLEANR, '', text)
        return cleantext


if __name__ == '__main__':
    import config  # Хранит конфигурационные перменные
    osBot = OsTelegramBot(config.token, config.os_url, config.os_key)
    print('\n', osBot.getTopicFromApi(), sep='')
