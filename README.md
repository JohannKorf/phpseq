
# phpseq — PHP → PlantUML sequence diagrams

phpseq scans a PHP project, builds a static call graph, and renders a PlantUML sequence diagram for an entrypoint method (e.g., `App\Service\Foo::bar`).

## Install

```bash
composer install
```

## Usage

```bash
php bin/phpseq generate   --src=./app/src   --entry='App\\Service\\Foo::bar'   --depth=3   --out=diagram.puml
```

Then preview with PlantUML or VS Code PlantUML extension.

## PHAR build

```bash
composer install
vendor/bin/box compile
./phpseq.phar generate --src=./app/src --entry='App\\Service\\Foo::bar' --out=diagram.puml
```

## Notes / Limitations
* Static analysis only (no runtime). Dynamic dispatch on variables, magic calls, and reflection may be unresolved.
* Names are resolved at **class-level participants** for simplicity.
* You can tweak filters and renderer in `src/` to suit your codebase.
