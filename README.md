# Antarctic PHP Lightweight Web Framework

This repository contains a lightweight PHP web framework optimized for PHP 8.2. It is designed to provide a simple and efficient foundation for developing modern web applications with an object-oriented approach.

## Key Features

- **PHP 8.2 Optimized**: This framework leverages the latest features of PHP 8.2, ensuring optimal performance and compatibility.
- **Twig 3.0 Templating**: Utilizes Twig 3.0 for fast and secure template rendering.
- **Lightweight Structure**: Focused on simplicity and minimalism, providing only the core features needed for most web applications.
- **MVC Architecture**: Built-in support for MVC (Model-View-Controller) architecture, allowing for organized and maintainable code.
- **Routing**: Flexible routing system for handling HTTP requests and directing them to the appropriate controllers.
- **Future REST API Support**: Planned support for RESTful controllers to easily build REST APIs.
- **Dependency Injection (DI) Container**: Upcoming support for a DI container to manage dependencies more efficiently, promoting a clean and modular codebase.
- **Composer Integration**: Handles dependencies and autoloading via Composer for easy management and integration of third-party libraries.
- **Docker Configuration**: Includes a Docker configuration file for quick setup and deployment, featuring Xdebug support for enhanced debugging capabilities.

## Getting Started

### Prerequisites

- **PHP 8.2 or higher** is required to run this framework.
- **Composer** should be installed to manage dependencies.
- **Docker** and **Docker Compose** are needed for the development environment setup.

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/your-repo-name.git
   cd your-repo-name
   ```

2. **Install dependencies using Composer**:
   ```bash
   cd src 
   composer install
   ```

3. **Set up Docker environment**:
   Make sure Docker and Docker Compose are installed on your system. Then, run the following command to start the application in a Docker container:
   ```bash
   docker-compose up -d
   ```

   This command will build and start the application and database containers defined in the `docker-compose.yml` file.

### Configuration

1. **Environment Variables**:
   Configure your environment variables by copying `.env.example` to `.env` and adjusting the settings to match your environment.

2. **Docker Configuration**:
   The `docker-compose.yml` file is pre-configured to set up a PHP application container with Apache and a database container (MySQL or PostgreSQL, configurable). Ensure your `.env` file matches the database settings you want to use.

### Usage

- **Access the application**: Once the Docker containers are up and running, you can access the application in your web browser at `http://localhost`.
- **Xdebug**: The development environment includes Xdebug for debugging purposes. Configure your IDE to connect to Xdebug on the appropriate port (usually 9000 or 9003).

### Future Roadmap

- Implement REST API controllers to support building RESTful services.
- Add Dependency Injection (DI) container support for better dependency management and testability.
- Expand the documentation with tutorials and usage examples.

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to discuss any changes you would like to see.

1. Fork the repository
2. Create a new branch (`git checkout -b feature-branch`)
3. Make your changes
4. Commit your changes (`git commit -am 'Add new feature'`)
5. Push to the branch (`git push origin feature-branch`)
6. Open a pull request

## License

This project is open-source and available under the [GNU GENERAL PUBLIC LICENSE Version 3](LICENSE).

## Contact

For any questions or suggestions, please feel free to reach out to [csekme.krisztian@outlook.com](mailto:csekme.krisztian@outlook.com).


# How to configure

For Database connection create an **.env** file on the root and define the following properties.

Application configuration file

```json

{
    "administrator": {
        "email": "xy@server.com"
    },
    "application": {
      "name": "My Production",
      "description": "This is my application based on Antrarctic Web Framework",
      "secretKey": "MY-SECRET-KEY"
    },
   "framework": {
      "cache": true,
      "showErrors": false,
      "useCoreControllers": false
   },
    "smtp": {
        "debug": 0,
        "host": "smtp.mail.com",
        "auth": true,
        "username": "username",
        "password": "your-password",
        "secure": "ssl",
        "port": 465,
        "from": "noreply@mail.com",
        "alias": "Jhon Doe",
        "charset": "UTF-8",
        "method": 0,
        "enabled": true
    }
}

```

## VSCODE xdebug launcher
```json
{
    // Use IntelliSense to learn about possible attributes.
    // Hover to view descriptions of existing attributes.
    // For more information, visit: https://go.microsoft.com/fwlink/?linkid=830387
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}/src/html",
                "/var/www/Application": "${workspaceFolder}/src/Application",
                "/var/www/Framework": "${workspaceFolder}/src/Framework",
                
            }
        }
    ]
}
```

## Docker compose file

### Postgresql
```yaml
services:
  app:
    container_name: application-cont
    platform: linux/amd64
    build:
      context: ./docker/apache
      dockerfile: Dockerfile
    ports:
      - "80:80"
      - "443:443"
    extra_hosts:
      - "host.docker.internal:host-gateway"
    networks:
      - antarctic-web
    volumes:
      - ./src:/var/www
  database:
    container_name: application-db-cont
    platform: linux/amd64
    build:
      context: ./docker/${DATABASE}
      dockerfile: Dockerfile
    ports:
      - ${DATABASE_PORT}:${DATABASE_PORT}
    environment:
      - POSTGRES_USER=${DATABASE_USER} # The PostgreSQL user (useful to connect to the database)
      - POSTGRES_PASSWORD=${DATABASE_PASSWORD}
      - POSTGRES_DB=${DATABASE_NAME}
      - POSTGRES_INITDB_ARGS=--auth-host=md5 --locale=hu_HU.UTF-8
    networks:
      - antarctic-web
    volumes:
      - ~/.local/share/antarctic/data/:/var/lib/postgresql/data/

networks:
  antarctic-web:
    name: "antarctic-web"
    driver: bridge
```

#### MySQL or MariaDB
```yaml
services:
  app:
    container_name: application-cont
    platform: linux/amd64
    build:
      context: ./docker/apache
      dockerfile: Dockerfile
    ports:
      - "80:80"
      - "443:443"
    extra_hosts:
      - "host.docker.internal:host-gateway"
    networks:
      - antarctic-web
    volumes:
      - ./src:/var/www
  database:
    container_name: application-db-cont
    platform: linux/amd64
    build:
      context: ./docker/${DATABASE}
      dockerfile: Dockerfile
    ports:
      - ${DATABASE_PORT}:${DATABASE_PORT}
    environment:
      - MYSQL_ROOT_PASSWORD=${DATABASE_ROOT_PASSWORD}
      - MYSQL_DATABASE=${DATABASE_NAME}
      - MYSQL_USER=${DATABASE_USER}
      - MYSQL_PASSWORD=${DATABASE_PASSWORD}
    networks:
      - antarctic-web
    volumes:
      - ./docker/${DATABASE}/init.sql:/docker-entrypoint-initdb.d/init.sql

networks:
  antarctic-web:
    name: "antarctic-web"
    driver: bridge
```
# Create a new Project

Let's create a new project (your project) make a directory under src folder called Application
this will be your project root folder.

Your project folder should use the following strucutre:
```
Application
    Controllers
    Interceptors
    TwigExtensions
    Models
    Views
      Errors  
```

## Example of model
Let's suppose you have a table called person.
```sql
create table person
(
    name text   not null,
    age  bigint not null
);

```

```php 

<?php

namespace Application\Models;

use Framework\Dal;
use PDO;

/**
 * Person Model
 * @property string $name
 * @property int $age
 */
class TestModel extends Dal
{

    function save()
    {
        $sql = 'INSERT INTO person (name, age) values (:name, :age)';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(":name", $this->name);
        $statement->bindParam(":age", $this->age, PDO::PARAM_INT);
        return $statement->execute();
    }
}
```

## Example of a Controller
```php

<?php

namespace Application\Controllers;

use Framework\AbstractController;
use Framework\Controller as Controller;
use Framework\Path as Path;
use Framework\Response;
use Framework\ResponseBuilder;
use Application\Models\TestModel;

#[Path("/")]
class TestController extends Controller
{

    #[Path(method: AbstractController::POST)]
    function save(): Response
    {
        $model = new TestModel($this->request->getJson());
        if ($model->save()) {
        $builder = ResponseBuilder::create();
        return $builder
            ->setBody('Resource have been saved')
            ->addHeader('Content-Type: text/plain')
            ->setStatusCode(200)
            ->build();
        }

    }

    #[Path(method: AbstractController::GET)]
    function testAction(): Response
    {
        $response = new Response();
        $response->setBody("Hello, World!");
        return $response;
    }
}


```