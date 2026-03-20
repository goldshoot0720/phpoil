@echo off
cd /d D:\codes\codexs\phpoil
if not exist storage mkdir storage
C:\ServBay\packages\php\current\php.exe cron\fetch_daily.php >> storage\scheduled-fetch.log 2>&1