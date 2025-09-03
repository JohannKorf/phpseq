<?php declare(strict_types=1);

namespace PhpSeq\CLI;

var_dump(php_ini_loaded_file(), php_ini_scanned_files());

use PhpSeq\Scanner\ProjectScanner;
use PhpSeq\Render\PlantUMLRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'phpseq:generate', description: 'Scan PHP and emit PlantUML sequence diagram')]
class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('src', null, InputOption::VALUE_REQUIRED, 'Source directory to scan', getcwd())
             ->addOption('entry', null, InputOption::VALUE_REQUIRED, 'Entrypoint method FQN, e.g. App\\Service\\Foo::bar')
             ->addOption('depth', null, InputOption::VALUE_REQUIRED, 'Max recursion depth from entry', '3')
             ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output .puml file path', 'diagram.puml')
             ->addOption('include-vendor', null, InputOption::VALUE_NONE, 'Include vendor/ in scan')
             ->addOption('max-nodes', null, InputOption::VALUE_REQUIRED, 'Limit number of nodes to avoid explosion', '500')
             ->addOption('group-namespaces', null, InputOption::VALUE_REQUIRED, 'Group participants by top-level namespace (comma separated)', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $src = (string)$input->getOption('src');
        $entry = (string)$input->getOption('entry');
        $depth = (int)$input->getOption('depth');
        $out = (string)$input->getOption('out');
        $includeVendor = (bool)$input->getOption('include-vendor');
        $maxNodes = (int)$input->getOption('max-nodes');
        $groupNs = array_filter(array_map('trim', explode(',', (string)$input->getOption('group-namespaces'))));

        $scanner = new ProjectScanner($src, $includeVendor, $maxNodes);
        $graph = $scanner->scan();

        $entries = [];
        if ($entry) {
            if (!$graph->hasMethod($entry)) {
                $output->writeln(sprintf('<error>Entrypoint %s not found in scanned sources.</error>', $entry));
                return Command::FAILURE;
            }
            $entries[] = $entry;
        } else {
            $entries = $graph->allPublicMethods();
            if (!$entries) {
                $output->writeln('<error>No public methods found in scanned sources.</error>');
                return Command::FAILURE;
            }
        }

        $renderer = new PlantUMLRenderer($groupNs);

        // If user supplied a single output file, treat it as directory prefix
        $outPath = $out;
        if (is_file($outPath) || str_ends_with($outPath, '.puml')) {
            $outDir = dirname($outPath);
        } else {
            $outDir = rtrim($outPath, DIRECTORY_SEPARATOR);
        }
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        $count = 0;
        foreach ($entries as $entryFqn) {
            $uml = $renderer->renderSequence($graph, $entryFqn, $depth);

            // sanitize filename: App\Service\Foo::bar -> App.Service.Foo.bar.puml
            $fileName = str_replace(['\\', ':'], ['.', '.'], $entryFqn) . '.puml';
            $filePath = $outDir . DIRECTORY_SEPARATOR . $fileName;

            file_put_contents($filePath, $uml);
            $count++;
            $output->writeln(sprintf('<info>Wrote %s</info>', $filePath));
        }

        $output->writeln(sprintf('<info>Generated %d diagrams</info>', $count));
        return Command::SUCCESS;

    }
}
