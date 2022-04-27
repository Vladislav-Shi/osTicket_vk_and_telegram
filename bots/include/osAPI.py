import re
import requests
import json


class osAPI():
    '''
    Класс в котором хранятся все методы доступные для взаимодействия с
    API osTicket. Предоставляет инструменты ботам для взаимодействия с системой
    '''
    # на stackowerflow было написано что нужно скомпилировать ОДИН раз
    CLEANR = re.compile('<.*?>')  # Для чистки от тегов

    def __init__(self, os_url: str, os_token: str, user_agent='bot-to-osAPI') -> None:
        '''
        Инициирует обьект класса
        '''
        self.os_url = os_url
        self.os_token = os_token
        self.user_agent = user_agent
        self.headers = {'X-API-Key': self.os_token,
                        'User-Agent': self.user_agent,
                        'content-type': 'application/json', }

    def create_ticket(self, message: str,
                      user_id: str,
                      username: str,
                      subject='',
                      attachments={},
                      category=0):
        '''
        Отпавляет запрос на создание заявки
        Возвращает словарь 'status' = 201, заявка создана, 'ticket' - номер заявки
        '''
        if subject == '':
            subject = username
        data = {
            'function': 'createTicket',
            'args':
            {
                'name': username,
                'email': user_id,
                'subject': subject,
                'message': message,
                'topicId': category,
                'ip': '83.149.21.24',
                'attachments': attachments,
            },
            'key': self.os_token
        }
        response = requests.post(self.os_url + 'createTicket', json=data,
                                 headers=self.headers, verify=False)
        return response.json()

    def add_ansver(self,
                   ticket_number: str,
                   message: str,
                   username: str,
                   user_id: str,
                   attachments={},
                   ):
        '''
        Добавляет ответ на заявку
        Возвращает словарь 'status' = 201, ответ добавлен, 'message'
        'status' = 400 - что то не так
        '''
        data = {
            'function': 'addToTicket',
            'args': {
                'ticketNumber': ticket_number,
                'name': username,
                'email': user_id,
                'message': message,
                'ip': '127.0.0.1',
                'attachments': attachments
            },
            'key': self.os_token,
        }
        response = requests.post(
            self.os_url + 'addToTicket', json=data, headers=self.headers, verify=False)
        return response.json()

    def get_story_messages(self, ticket_number: str, username: str):
        '''
        получает истории сообщений пользователя
        Возвращает словарь 'status' = 200, успешно, 'message' - массив сообщений
        'status' = 400 - что то не так
        '''
        data = {'function': 'getMessageStory',
                'args': {
                    'username': username,
                    'ticketNumber': ticket_number,
                },
                'key': self.os_token}
        response = requests.post(
            self.os_url + 'getMessageStory', json=data, headers=self.headers, verify=False)
        return response.json()

    def get_category(self):
        '''
        Возвращает список категорий в виде массвиа
        Возвращает словарь 'status' = 200, успешно, 'topics' -список категорий в виде массвиа
        '''
        data = {'function': 'getTopicList',
                'args': {},
                'key': self.os_token, }
        response = requests.post(
            self.os_url + 'getTopicList', json=data, headers=self.headers, verify=False)
        return response.json()

    def get_ticket_owner(self, ticket_number, username):
        '''
        Получить имя и id владельца заявки
        возвращает id без словаря
        '''
        data = {'function': 'checkTicketOwner',
                'args': {
                    'username': username,
                    'ticketNumber': ticket_number},
                'key': self.os_token}
        response = requests.post(
            self.os_url + 'checkTicketOwner', json=data, headers=self.headers, verify=False)
        return self.html_clear(response.text)

    def get_user_ticekts(self, username):
        '''
        Получает все открытые заявки пользователя
        username - имя пользователя чьи заявки получаем
        Возвращает словарь 'status' = 200, успешно, 'tickets' - номера заявок
        '''
        data = {'args': {
            'username': username
        },
            'key': self.os_token
        }
        response = requests.post(
            self.os_url + 'getUserTickets', json=data, headers=self.headers, verify=False)
        return response.json()

    @staticmethod
    def html_clear(text: str) -> str:
        """
        Очищает переданный в параметре текст от всех тегов
        """
        cleantext = re.sub(osAPI.CLEANR, '', text)
        return cleantext


if __name__ == '__main__':
    import config
    os_bot = osAPI(config.os_url, config.os_token)
    print(os_bot.get_category())

