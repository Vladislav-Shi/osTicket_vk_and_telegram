import vk_api
import random
import json
import bots.include.config as config
from vk_api import keyboard


class VkBot:
    '''
    Бот для vk
    '''

    def __init__(self, token) -> None:
        self.vk_session = vk_api.VkApi(token=token)
        self.vk = self.vk_session.get_api()
        self.token = token

    def send_message(self, user_id, token, message, attachment="", keyboard = ''):
        '''
        Отправляет сообщение пользователю
        '''
        self.vk.messages.send(access_token=token, user_id=str(
            user_id), message=message, attachment=attachment, random_id=random.getrandbits(64),
            keyboard=self.get_action_keyboard().get_keyboard())

    def get_answer(self, body):
        message = "Привет, я новый бот!"
        return message

    def action_message(action: str):
        '''
        Сообщение которое выдаст в зависимости от действия
        то есть action = create, add, history, help
        '''
        if action == 'create':
            pass
        elif action == 'add':
            pass
        elif action == 'histori':
            pass
        elif action == 'help':
            pass
        else:
            pass

    def create_answer(self, data: dict):
        '''
        Создает сообщение для отправки пользователю
        '''
        user_id = data['from_id']
        # в этом массиве полезная нагрузка которая видна только боту
        # Берется собственно из параметра payload кнопки
        if 'payload' in data:
            payload = json.loads(data['payload'])
            if 'action' in payload:
                pass
        message = self.get_answer(data['text'].lower())
        self.send_message(user_id, self.token, message)

    def get_action_keyboard(self):
        '''
        Метод создает клавиатуру с доступными действиями
        '''
        vk_action_keyboard = keyboard.VkKeyboard()
        vk_action_keyboard.add_button(label='Создать заявку', payload={
                                      'type': 'text', 'action': 'create'})
        vk_action_keyboard.add_button(label='Добавить ответ в заявку', payload={
                                      'type': 'text', 'action': 'add'})
        vk_action_keyboard.add_button(label='Посмотреть историю сообщений', payload={
                                      'type': 'text', 'action': 'history'})
        vk_action_keyboard.add_button(label='Помощь', payload={
                                      'type': 'text', 'action': 'help'})
        return vk_action_keyboard

    def inline_keyboard():
        '''
        Создает клавиатуру привязанную к сообщению
        '''
        pass


vk_bot = VkBot(config.vk_key)
