# Antarctic PHP Lightweight Web Framework

This repository contains a lightweight PHP web framework optimized for PHP 8. It is designed to provide a simple and efficient foundation for developing modern web applications with an object-oriented approach.

## Key Features

- **PHP 8 Optimized**: This framework leverages the latest features of PHP 8, ensuring optimal performance and compatibility.
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

- **PHP 8.0 or higher** is required to run this framework.
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



















# antarcitc
Antarctic PHP Web Framework


# How to configure

For Database connection create an **.env** file on the root and define the following properties.
```ini
    DATABASE=mariadb
    DATABASE_USER=csk
    DATABASE_PASSWORD=csk
    DATABASE_ROOT_PASSWORD=csk
    DATABASE_NAME=csk
    DATABASE_PORT=3306
```

Application configuration file

```json

{
    "administrator": {
        "email": "xy@server.com"
    }
}

```
