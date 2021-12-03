import pymysql
from pymysql.err import Error
import config


class BdWork:
    def __init__(self, bd_config) -> None:
        self.__connection = None
        try:
            self.__connection = pymysql.connect(
                host=bd_config['host'],
                database=bd_config['bd_name'],
                user=bd_config['username'],
                passwd=bd_config['password']
            )
            print('connect_succsessful')
        except Error as er:
            print(f"The error '{er}' occurred")

    def createLogTable(self, table_name: str = 'logtable'):
        """
        @param: table_name: str -- имя таблицы
        Метод солздает таблицу для логирования сообщений от бота в базе данных муsql
        Перед созданием проверяет имеется ли в базе таблица с именем log_table
        если нету, то созвдет, если есть, то ничего не делает
        Валидация походу автоматом)
        """
        cur = self.__connection.cursor()
        if not self.__checkTable(table_name):
            cur.execute(f"CREATE TABLE {self.__tableNameValidete(table_name)} (id INT NOT NULL AUTO_INCREMENT, message_text TEXT NULL, \
            send_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, user_id VARCHAR(10) NOT NULL, \
            response TEXT NULL, PRIMARY KEY (id))")

    def __checkTable(self, table_name: str = 'logtable'):
        cur = self.__connection.cursor()
        cur.execute(
            f"SHOW TABLES LIKE '{self.__tableNameValidete(table_name)}'")
        row = cur.fetchall()
        if len(row) == 0:
            return False
        else:
            return True

    def addToLogTable(self, params: dict, table_name: str = 'logtable'):
        """
        Добавляет запись в одну из выбраных таблиц логов
        Args:
            params (dict):
                params['message_text']
                params['user_id']
                params['response']
            table_name (str, optional):. Defaults to 'logtable'.
        """
        cur = self.__connection.cursor()
        query = f"INSERT INTO {self.__tableNameValidete(table_name)} (message_text, user_id, response) VALUES (%s, %s, %s)"
        result = cur.execute(
            query,
            (params['message_text'], params['user_id'], params['response']))
        print('result ', result)
        self.__connection.commit()

    def __tableNameValidete(self, table_name):
        """
        Удаляет все пробелы и нехорошие знаки, которые не могут быть в названии
        (Для других параметров следует использвать возможности cursor.execute())
        Args:
            table_name названия таблиц и столбцов

        Returns:
            строка с именем
        """
        return ''.join(chr for chr in table_name if chr.isalnum())


if __name__ == '__main__':
    bd_work = BdWork(config.bd_config)
    print(bd_work.__connection)
    # bd_work.createLogTable()
