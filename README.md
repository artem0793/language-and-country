# Installation

1) Create drupal 8 composer project

`composer create-project drupal-composer/drupal-project:8.x-dev some-dir --stability dev --no-interaction`

`cd some-dir`

2) Create config folders

`mkdir config/sync`

3) Add github repository to composer.json

```json
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.drupal.org/8"
        },
        {
            "type": "vcs",
            "url": "https://github.com/artem0793/language-and-country"
        }
    ],
```

4) Add language_and_country module.

`composer require drupal/language_and_country`

5) Install dependencies.

`composer install`

`cd web` - Configure web server

6) Install site

`drush si ... -y`

7) Enable the module

`drush en language_and_country -y`

8) Check the country/language switchers in the header. 
