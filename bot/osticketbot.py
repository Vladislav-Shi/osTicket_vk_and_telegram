import requests
import re
import json
from telebot.types import PhotoSize


class OsTelegramBot:

    def __init__(self, botkey, osUrl, osToken) -> None:
        """[summary]
        Args:
            botkey ([string]): ключ для доступа к боту
            osUrl ([type]): ссылка на API остикет (ссылка типа .../api/tickets.json)
            osToken ([type]): ключ чтобы API osTicket могло обработаь запрос (брать в админке osTicket)
        """
        self.botkey = botkey
        self.osUrl = osUrl
        self.osToken = osToken

    def parserMessage(self, message: str):
        """[summary]

        Проверяет полученное сообщение и на его основе выбирает что нужно сделать
        Args:
            message ([type]): [description]

        Returns:
            Возвращается два значения
            1) Сообщение для пользователя
            2) функция для обработки (если таковая нужна)
        """
        lineMessage = message.splitlines()
        command = lineMessage[0].split()
        if command[0] == '-help':
            return self.getHelpText()
        elif command[0] == '-create':
            if len(lineMessage) > 1:
                return 'СОЗДАНИЕ ТИКЕТА'
            else:
                return 'Введите "-help create" для помощи по команде'
            return 1
        elif command[0] == '-add':
            if len(command) > 1 and re.search(r'^[0-9]{6}', command[1]):
                return 'Билет с таким id найден'
            else:
                return 'Не найдено, проверьте правильность введенного вами идентификатора или введите "-help add" для просмотра помощи по команде'
        elif command[0] == '-get':
            if len(command) > 1 and re.search(r'^[0-9]{6}', command[1]):
                return 'Билет с таким id найден'
            else:
                return 'Не найдено, проверьте правильность введенного вами идентификатора или введите "-help get" для просмотра помощи по команде'
        else:
            return 'Введите "-help" для помощи по командам'

    def createTicket(self, userData, message, attachments={}, userAgent='telegram-API/0.0.2'):
        """[summary]
        отправляет curl апишке osticket на создание тикета

        Args:
            userData ([dict]): данные типа data['message']['chat']
            message ([type]): само тело тикета
            attachments (dict, optional): [description]. массив типа [{<имя файла> : <сообщение в кодировка base64>}]
            userAgent (str, optional): [description]. Defaults to 'telegram-API/0.0.1'.

        Returns:
            Возвращает строку с id созданной заявки или 0 в случае неудачи
        """
        print('createTicket run:')
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
                'ip': '127.0.0.1',
                'attachments': attachments,
            },
            'key': self.osToken,
        }
        print(data)
        r = requests.post(self.osUrl,
                          json=data, headers=headers, verify=False)
        print('r', r)
        if r.status_code == 201:
            return r.text
        else:
            return r.text

    def addToTicket(self, ticketNumber, message, userData, attachments={}, userAgent='telegram-API/0.0.1'):
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
                'attachments': {attachments}
            },
            'key': self.osToken,
        }
        response = requests.post(
            self.osUrl, json=data, headers=headers, verify=False)
        print('addToTicket response.text: ',response.text)
        return json.loads(response.text)

    def sendMessage(self, chat_id, text):
        """[summary]
        Отпрпавляет телеграм API запрос на оправку сообщения от бота
        Args:
            chat_id ([type]): id чата куда отправлять
            text ([type]): текст сообщения

        Returns:
            [type]: статус код ответа
        """
        method = "sendMessage"
        url = f"https://api.telegram.org/bot{self.botkey}/{method}"
        data = {"chat_id": chat_id, "text": text}
        response = requests.post(url, json=data, verify=False)
        return response.status_code

    def getStoryMessage(self, username, ticketNumber):
        """[summary]

        Args:
            username (str): [description]
            ticketNumber (int): [description]

        Returns:
            [type]: [description]
        """
        data = {'function': 'getMessageStory',
                'args': {
                    'username': username,
                    'ticketNumber': ticketNumber,
                },
                'key': self.osToken, }
        response = requests.post(self.osUrl, json=data, verify=False)
        print(response.status_code, response.text)
        return response.text

    def getHelpText(self, typeText='all'):
        """[summary]
        Возвращает все варианты подсказок в зависимости от контекста применения

        Args:
            typeText (str, optional): [description]. Defaults to 'all'.

        Returns:
            [type]: [description]
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
        print('getTicketOwner is run')
        data = {'function': 'checkTicketOwner',
                'args': {
                    'username': username,
                    'ticketNumber': ticket_number},
                'key': self.osToken}
        response = requests.post(self.osUrl, json=data, verify=False)
        return response.text

