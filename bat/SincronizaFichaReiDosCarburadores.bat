@echo off
set php_path=C:\xampp5635\
set empresa=ReiDosCarburadores

::echo %php_path%
%php_path%php\php %php_path%htdocs\srv\gravaFicha.php -e %empresa%
