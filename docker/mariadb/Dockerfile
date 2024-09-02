# A bázis kép megadása
FROM mariadb:10.9

# Egyedi beállítások, pl. konfigurációs fájlok hozzáadása (opcionális)
# COPY my-custom.cnf /etc/mysql/conf.d/

# Az INIT scriptek hozzáadására szolgáló mappa létrehozása (opcionális)
RUN mkdir -p /docker-entrypoint-initdb.d

# INIT scriptek és egyéb szükséges fájlok hozzáadása (opcionális)
#COPY init-db.sql /docker-entrypoint-initdb.d/

# Környezeti változók beállítása (opcionális)
#ENV MYSQL_ROOT_PASSWORD=my-secret-pw
# A többi környezeti változó, mint például MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD, stb., szintén itt adható meg.

# A MariaDB portjának kinyitása
EXPOSE 3306

# Az adatbázis indítása a MySQL szerverrel
CMD ["mysqld"]
