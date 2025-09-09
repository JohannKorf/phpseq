<?php
namespace PhpSeq\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    // Match Symfony style with vendor prefix
    protected static $defaultName = 'phpseq:generate';

    protected function configure(): void
    {
        $this
            ->setDescription('Generate PlantUML sequence diagrams from PHP source')
            ->setHelp($this->getHelpText())
            ->addOption('src', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Source directory (repeat per repo/component)')
            ->addOption('entry', null, InputOption::VALUE_REQUIRED,
                'Entrypoint method (e.g. App\\Service\\Foo::bar)')
            ->addOption('depth', null, InputOption::VALUE_REQUIRED,
                'Maximum call depth to follow [default: 3]', 3)
            ->addOption('out', null, InputOption::VALUE_REQUIRED,
                'Output PlantUML file (.puml)')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Exclude paths (e.g. */tests/*, */vendor/*)')
            ->addOption('edge-label', null, InputOption::VALUE_OPTIONAL,
                'Label edges: "name" = method name, "count" = aggregate call counts, "all" = all calls', 'name')
            ->addOption('max-edges', null, InputOption::VALUE_REQUIRED,
                'Limit number of edges in output')
            ->addOption('component-drill', null, InputOption::VALUE_REQUIRED,
                'Focus diagram on one component')
            ->addOption('show-components', null, InputOption::VALUE_NONE,
                'Show calls aggregated between components')
            ->addOption('composer-map', null, InputOption::VALUE_OPTIONAL,
                'Use composer.json/lock mapping to resolve namespaces → component names');
    }

    private function getHelpText(): string
    {
        return <<<EOT
The <info>phpseq:generate</info> command scans PHP source and builds PlantUML sequence diagrams.

Usage:
  php bin/phpseq phpseq:generate [options]

Options:
  --src=PATH                Source directory (repeat per repo/component)
  --entry=FQN               Entrypoint method (e.g. App\Service\Foo::bar)
  --depth=N                 Maximum call depth to follow [default: 3]
  --out=FILE                Output PlantUML file (.puml)
  --exclude=GLOB            Exclude paths (e.g. */tests/*, */vendor/*)
  --edge-label[=MODE]       Label edges:
                              "name"  = show method name (default)
                              "count" = aggregate call counts
                              "all"   = show all call instances
  --max-edges=N             Limit number of edges in output
  --component-drill=NAME    Focus diagram on one component
  --show-components         Show calls aggregated between components
  --composer-map[=FILE]     Use composer.json/lock mapping for component names

Examples:
  php bin/phpseq phpseq:generate --src=./app/src \\
                                 --entry='App\Service\Foo::bar' \\
                                 --depth=3 \\
                                 --out=diagram.puml

  php bin/phpseq phpseq:generate --src=repo1/src --src=repo2/lib \\
                                 --show-components \\
                                 --edge-label=count \\
                                 --exclude="*/tests/*"

  php bin/phpseq phpseq:generate --src=./services \\
                                 --entry='Billing\\InvoiceService::generate' \\
                                 --component-drill=Billing


The <info>phpseq:components</info> command generates a component communication diagram,
aggregating calls between repositories / modules instead of individual classes.

Usage:
  php bin/phpseq phpseq:components [options]

Options:
  --src=PATH                Source directories for all components (repeat per repo)
  --exclude=GLOB            Exclude paths (e.g. */tests/*, */vendor/*)
  --composer-map[=FILE]     Use composer.json/lock mapping to resolve namespaces → component names
  --out=FILE                Output PlantUML file (.puml)
  --max-edges=N             Limit number of edges in output
  --edge-label=count        Show call counts between components

Examples:
  php bin/phpseq phpseq:components --src=repo1/src --src=repo2/lib \\
                                   --exclude="*/tests/*" \\
                                   --out=components.puml
EOT;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // existing execution logic...
        return Command::SUCCESS;
    }
}
