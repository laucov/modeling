# Laucov's Modeling Library

Library for data modeling and database management.

## Installation

Use Composer to install the package available on Packagist:

```shell
composer require laucov/modeling
```

## Usage

Instructions are under development. Please refer to PHPUnit suites under `/tests` or use PHPDocumentor to consult the documented API.

## Future breaking changes

This library is currently under development and hasn't lauched a `1.0` version yet. Therefore, its minor updates may contain smaller breaking changes.

This section documents changes that are already predicted to happen before the first major update.

### Refactoring of `AbstractModel`

`AbstractModel` is currently a god object and will be dismembered in the future.

More specifically, it will keep business logic functionalities - like validation - while a new `AbstractRepository` class will provide the interface to the `Table` object from `laucov/db`.

`AbstractDatabaseRule` will also change to work with repositories instead of models. This will remove the possibility of runtime circular references to `AbstractModel` objects - which may cause loops if the `ReadOnlyModelInterface` is violated.
