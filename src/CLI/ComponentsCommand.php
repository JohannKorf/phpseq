<?php

namespace PhpSeq\CLI;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSeq\Analysis\ComponentGraph;
use PhpSeq\Renderer\ComponentUMLRenderer;
use PhpSeq\Scanner\ProjectScanner;

#[AsCommand(name: 'phpseq:components', description: 'Generate a PlantUML component communications diagram')]
final class ComponentsCommand extends Command
{
    private const OPT_DRILLDOWN_ALL = 'drilldown-all';

    protected function configure(): void
    {
        $this
            ->addOption('src', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Source root that contains multiple repos (repeatable: --src=/root1 --src=/root2)')
            ->addOption('repo-src', null, InputOption::VALUE_REQUIRED,
                'Comma-separated per-repo source dirs (e.g., app,src,lib)', 'src')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED,
                'Comma-separated exclude globs (e.g., */vendor/*,*/tests/*)', '')
            ->addOption('out', null, InputOption::VALUE_REQUIRED,
                'Output .puml file', 'components.puml')
            ->addOption('edge-label', null, InputOption::VALUE_REQUIRED,
                'name|counts|all', 'name')
            ->addOption('edge-detail', null, InputOption::VALUE_REQUIRED,
                'aggregate|all', 'aggregate')
            ->addOption('prefer-composer-name', null, InputOption::VALUE_NONE,
                'Prefer composer package name as component label')
            ->addOption('drilldown-dir', null, InputOption::VALUE_REQUIRED,
                'Directory to write per-component drilldowns (optional)')
            ->addOption(self::OPT_DRILLDOWN_ALL, null, InputOption::VALUE_NONE,
                'Write drill-down file for every component, even if it has no edges')
            ->addOption('edge-show-endpoints', null, InputOption::VALUE_NONE,
                'Show endpoint paths on edges (where available)')
            ->addOption('ignore-noise', null, InputOption::VALUE_NONE,
                'Ignore well-known noise hosts (fonts.googleapis.com, www.w3.org, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $srcRoots = $input->getOption('src') ?? [];
        if (!is_array($srcRoots)) {
            $srcRoots = array_filter(array_map('trim', explode(',', (string)$srcRoots)));
        }

        $repoSrc = $input->getOption('repo-src');
        $repoSrc = is_array($repoSrc)
            ? array_values(array_filter(array_map('trim', $repoSrc)))
            : array_values(array_filter(array_map('trim', explode(',', (string)$repoSrc))));

        $excludeOpt = $input->getOption('exclude');
        $excludeGlobs = is_array($excludeOpt)
            ? array_values(array_filter(array_map('trim', $excludeOpt)))
            : array_values(array_filter(array_map('trim', explode(',', (string)$excludeOpt))));

        $outFile     = (string) $input->getOption('out');
        $edgeLabel   = (string) $input->getOption('edge-label');
        $edgeDetail  = (string) $input->getOption('edge-detail');
        $preferPkg   = (bool)   $input->getOption('prefer-composer-name');
        $drillDir    = $input->getOption('drilldown-dir') ? (string)$input->getOption('drilldown-dir') : null;
        $writeEmpty  = (bool)   $input->getOption(self::OPT_DRILLDOWN_ALL);
        $showEndpoints = (bool) $input->getOption('edge-show-endpoints');
        $ignoreNoise   = (bool) $input->getOption('ignore-noise');

        $scanner = new ProjectScanner($showEndpoints, $ignoreNoise);
        $graph   = new ComponentGraph();

        foreach ($srcRoots as $root) {
            $g = $scanner->scanRoot($root, $repoSrc, $excludeGlobs);
            $graph->merge($g);
        }

        $renderer = new ComponentUMLRenderer($preferPkg);
        $uml = $renderer->render($graph, 'Component Communications', '', $edgeLabel, $edgeDetail, null);
        file_put_contents($outFile, $uml);
        $output->writeln('<info>Wrote ' . $outFile . '</info>');

        if ($drillDir) {
            @mkdir($drillDir, 0777, true);
            foreach ($graph->getComponents() as $comp) {
                $sub = $graph->subgraphForComponent($comp);
                if (!$writeEmpty && $sub->edgeCount() === 0) {
                    continue;
                }
                $file = rtrim($drillDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                      . preg_replace('/[^A-Za-z0-9_\-]+/', '_', $comp) . '.puml';
                $umlSub = $renderer->render($sub, $comp . ' communications', '', $edgeLabel, $edgeDetail, null);
                file_put_contents($file, $umlSub);
                $output->writeln('<info>Wrote ' . $file . '</info>');
            }
        }

        return Command::SUCCESS;
    }
}
