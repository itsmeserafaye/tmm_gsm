@echo off
cd /d C:\xampp\htdocs\tmm
php admin\cli\scheduler.php hourly >> logs\hourly_scheduler.log 2>&1
