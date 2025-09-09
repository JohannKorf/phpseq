<?php declare(strict_types=1);
namespace PhpSeq\CLI;

use PhpSeq\Scanner\ProjectScanner;
use PhpSeq\Renderer\ComponentUMLRenderer;
use PhpSeq\Analysis\ComponentGraph;
use PhpSeq\Util\ComposerNameCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'phpseq:components',
    description: 'Generate a PlantUML component communications diagram',
    aliases: ['components']
)]
    
final class ComponentsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('src', null, InputOption::VALUE_REQUIRED, 'Root folder with REPO subfolders', getcwd())
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output .puml path', 'components.puml')
            ->addOption('edge-label', null, InputOption::VALUE_REQUIRED, 'counts|unique', 'counts')
            ->addOption('edge-detail', null, InputOption::VALUE_REQUIRED, 'aggregate|all', 'aggregate')
            ->addOption('max-edges', null, InputOption::VALUE_OPTIONAL, 'Limit number of edges (by weight/order)', '')
            ->addOption('repo-src', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of per-repo source dirs (e.g. src,app,lib)', '')
            ->addOption('exclude', null, InputOption::VALUE_OPTIONAL, 'Comma-separated glob patterns to exclude', '')
            ->addOption('prefer-composer-name', null, InputOption::VALUE_NONE, 'Use composer.json name if available for component label')
            ->addOption('drilldown-dir', null, InputOption::VALUE_REQUIRED, 'Directory to write per-component diagrams', 'components');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $srcRoot = (string)$input->getOption('src');
        $outPath = (string)$input->getOption('out');
        $edgeLabel = (string)$input->getOption('edge-label');
        $edgeDetail = (string)$input->getOption('edge-detail');
        $maxEdgesOpt = (string)$input->getOption('max-edges');
        $maxEdges = $maxEdgesOpt !== '' ? (int)$maxEdgesOpt : null;
        $repoSrc = array_filter(array_map('trim', explode(',', (string)$input->getOption('repo-src'))));
        $exclude = array_filter(array_map('trim', explode(',', (string)$input->getOption('exclude'))));
        $preferComposer = (bool)$input->getOption('prefer-composer-name');
        $drillDir = (string)$input->getOption('drilldown-dir');

        $scanner = new ProjectScanner($srcRoot);
        if ($exclude) $scanner->setExcludeGlobs($exclude);
        if ($repoSrc) $scanner->setRepoSrc($repoSrc);
        $graph = $scanner->scan(null, 99);

        // Build component mapping (repo name or composer name)
        $cache = new ComposerNameCache($srcRoot);
        $classToFile = $graph->getAllClassFiles();
        $classToComp = [];
        foreach ($classToFile as $class => $file) {
            $rel = ltrim(str_replace('\\', '/', substr($file, strlen(rtrim($srcRoot, '/').'/'))), '/');
            $parts = explode('/', $rel);
            $repo = $parts[0] ?? 'unknown';
            $label = $repo;
            if ($preferComposer) {
                $repoPath = rtrim($srcRoot, '/').'/'.$repo;
                $cached = $cache->get($repoPath);
                if ($cached === null) {
                    $composer = $repoPath . '/composer.json';
                    if (is_file($composer)) {
                        $data = json_decode((string)@file_get_contents($composer), true);
                        if (!empty($data['name'])) {
                            $name = (string)$data['name'];
                            $label = preg_replace('#^[^/]+/#', '', $name);
                            $cache->set($repoPath, $label);
                        }
                    }
                } else {
                    $label = $cached;
                }
            }
            $classToComp[$class] = $label;
        }
        $cache->save();

        $cg = new ComponentGraph();
        foreach ($classToComp as $class => $comp) $cg->addClassToComponent($comp, $class);

        foreach ($graph->getAllMethods() as $fromMethod => $_) {
            [$fromClass] = explode('::', $fromMethod, 2);
            $fromComp = $classToComp[$fromClass] ?? '';
            foreach ($graph->getCalls($fromMethod) as $toMethod) {
                [$toClass] = explode('::', $toMethod, 2);
                $toComp = $classToComp[$toClass] ?? '';
                if ($fromComp !== '' && $toComp !== '') {
                    $cg->addEdge($fromComp, $toComp, $fromMethod, $toMethod);
                }
            }
        }

        $renderer = new ComponentUMLRenderer();
        $title = 'Component Communications';
        $caption = 'Generated on ' . date('Y-m-d H:i:s');
        $uml = $renderer->render($cg, $title, $caption, $edgeLabel, $edgeDetail, $maxEdges);

        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, $uml);
        $output->writeln('<info>Wrote ' . $outPath . '</info>');

        // Drill-down per component
        if ($drillDir !== '') {
            @mkdir($drillDir, 0777, true);
            foreach ($cg->getComponents() as $comp) {
                $sub = new ComponentGraph();
                foreach ($classToComp as $class => $c) {
                    if ($c === $comp) $sub->addClassToComponent($comp, $class);
                }
                foreach ($graph->getAllMethods() as $fromMethod => $_) {
                    [$fromClass] = explode('::', $fromMethod, 2);
                    $fromComp2 = $classToComp[$fromClass] ?? '';
                    if ($fromComp2 !== $comp) continue;
                    foreach ($graph->getCalls($fromMethod) as $toMethod) {
                        [$toClass] = explode('::', $toMethod, 2);
                        $toComp2 = $classToComp[$toClass] ?? '';
                        if ($toComp2 === '') continue;
                        $sub->addEdge($fromComp2, $toComp2, $fromMethod, $toMethod);
                    }
                }
                $umlSub = $renderer->render($sub, $comp . ' communications', $caption, $edgeLabel, $edgeDetail, $maxEdges);
                $file = rtrim($drillDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_\-]+/', '_', $comp) . '.puml';
                file_put_contents($file, $umlSub);
                $output->writeln('<info>Wrote ' . $file . '</info>');
            }
        }

        return Command::SUCCESS;
    }
}
