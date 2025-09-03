<?php declare(strict_types=1);

namespace PhpSeq\CLI;

use PhpSeq\Scanner\ProjectScanner;
use PhpSeq\Renderer\PlantUMLRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'phpseq:generate')]
class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('src', null, InputOption::VALUE_REQUIRED, 'Source directory to scan', '.')
            ->addOption('entry', null, InputOption::VALUE_OPTIONAL, 'Entrypoint class::method')
            ->addOption('depth', null, InputOption::VALUE_OPTIONAL, 'Max call depth', 3)
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output file or directory', 'diagrams');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $src = $input->getOption('src');
        $entry = $input->getOption('entry');
        $depth = (int)$input->getOption('depth');
        $out = $input->getOption('out');

        $scanner = new ProjectScanner($src);
        $graph = $scanner->scan($entry ? [$entry] : null, $depth);

        $renderer = new PlantUMLRenderer();
        if (is_dir($out) || !str_ends_with($out, '.puml')) {
            if (!is_dir($out)) {
                mkdir($out, 0777, true);
            }
            foreach ($graph->getEntryPoints() as $ep) {
                $code = $renderer->render($graph, $ep, $depth);
                $file = $out . '/' . str_replace('\\', '_', $ep) . '.puml';
                file_put_contents($file, $code);
                $output->writeln("Wrote $file");
            }
        } else {
            $code = $renderer->render($graph, $entry, $depth);
            file_put_contents($out, $code);
            $output->writeln("Wrote $out");
        }

        return Command::SUCCESS;
    }
}
