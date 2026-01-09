@echo off
cd /d C:\xampp\htdocs\tmm
php admin\cli\scheduler.php nightly >> logs\nightly_scheduler.log 2>&1
