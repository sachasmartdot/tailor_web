[BEST PRACTISES]

## separated dev and production deps
-- compososer require `--dev` phpunit/phpunit
-- composer install `--no-dev` => wil omit packages installed by `require --dev`
-- identify potential issues and address them in `CI/CD pipelines` => `composer audit`
-- update regularly to ensure getting the latest bug fixes and security patches for the library I use
