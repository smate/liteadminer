# LiteAdminer

LiteAdminer is a lightweight, single-file PHP script for managing SQLite databases. Inspired by [Adminer](https://www.adminer.org/).

## Features

- View and manage SQLite database tables
- Simple and intuitive interface
- Execute custom SQL queries
- Edit table data

## Usage

1. Place the `index.php` file in your web server directory
2. Access it through your web browser
3. Connect to your SQLite database file

## Compression

To compress the script, you can use the `compact.php` script. This will remove comments and unnecessary whitespace to reduce the file size.

```bash
php compact.php index.php out.php
```

## Configuration

To connect to your SQLite database, you need to set the `dbFile` variable in the `index.php` file.

```php
$dbFile = 'database.sqlite';
```

### Language

To change the language, you need to set the `selectedLang` variable in the `index.php` file.

```php
$selectedLang = 'en';
```

## Security

Make sure to secure your SQLite database file and sensitive data and not expose it to the public internet.

## Requirements

- PHP 8.1 or higher
- SQLite extension enabled

## License

This project is open source and available under the Mozilla Public License (MPL) 2.0. See the [LICENSE](https://www.mozilla.org/en-US/MPL/) file for more details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.