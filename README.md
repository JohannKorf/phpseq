
# phpseq — PHP → PlantUML sequence diagrams

phpseq scans a PHP project, builds a static call graph, and renders a PlantUML sequence diagram for an entrypoint method (e.g., `App\Service\Foo::bar`).

## Install
## Versioning

Released PHAR binaries are versioned, e.g. `phpseq-0.5.1.phar`. Always use the binary matching the version in `composer.json` or `CHANGELOG.md`.


```bash
composer install
```

## Usage

```bash
php bin/phpseq phpseq:generate   --src=./app/src   --entry='App\\Service\\Foo::bar'   --depth=3   --out=diagram.puml
```

Then preview with PlantUML or VS Code PlantUML extension.

## PHAR build

```bash
composer install
vendor/bin/box compile
VERSION=$(jq -r .version composer.json)
mv phpseq.phar phpseq-$VERSION.phar
./phpseq-$VERSION.phar phpseq:generate --src=./app/src --entry='App\Service\Foo::bar' --out=diagram.puml
```

The PHAR build produces a standalone, versioned binary (e.g. `phpseq-0.5.1.phar`) that you can copy anywhere on your system.


## Notes / Limitations
* Static analysis only (no runtime). Dynamic dispatch on variables, magic calls, and reflection may be unresolved.
* Names are resolved at **class-level participants** for simplicity.
* You can tweak filters and renderer in `src/` to suit your codebase.
