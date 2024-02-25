<?php
phpinfo();
?>





[mysqld]
socket=/var/run/mysqld/mysqld.sock
[client]
socket=/var/run/mysqld/mysqld.sock

sudo pkill mysqld_safe

sudo chown -R $USER:$USER /var/www/html/magento


sudo nano /etc/apache2/sites-available/magento.conf

<VirtualHost *:80>
    ServerName magento.local
    ServerAlias magento.local 
    ServerAdmin magento.local
    DocumentRoot /var/www/html/magento
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
sudo a2ensite magento.local

sudo apt-get install apache2 php8.2 php8.2-cli \

