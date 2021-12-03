import subprocess
import sys
packages = [
    'pytelegrambotapi',
    'flask',
    'pymysql',
    'requests'
]
for package in packages:
    subprocess.check_call([sys.executable, '-m', 'pip', 'install', package])