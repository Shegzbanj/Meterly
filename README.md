\## 12. Database Setup and Import



Meterly uses MySQL/MariaDB for storing application data.



\### Database Information



\- Database name: `meterly\_db`

\- Database management system: MySQL/MariaDB

\- Database schema file: `database/meterly\_schema.sql`



\### Importing the Database



1\. Start Apache and MySQL from the XAMPP Control Panel.

2\. Open phpMyAdmin at `http://localhost/phpmyadmin`.

3\. Create a database named `meterly\_db`.

4\. Select the newly created database.

5\. Open the Import tab.

6\. Choose `database/meterly\_schema.sql` from the Meterly project folder.

7\. Click Import to create the database tables and structure.



The schema export contains database definitions without existing table records.



\### Database Configuration



Configure the database connection using `config/database.example.php` as a reference.



The local configuration file should be named `config/database.php` and contain the appropriate database connection settings.



Keep database credentials private. Do not upload actual passwords or sensitive configuration files to the public repository.



\### Important Note



The database schema provides the table structure required for setting up Meterly. It does not contain existing customer records or application data.

