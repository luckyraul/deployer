<?php

namespace Mygento\Deployer\Model;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Workflow
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function execute(string $filename, ?string $environment = null)
    {
        $this->readConfig($filename);
        $this->doBuild();
        $this->doDeploy($environment);
    }

    public function deploy(string $filename, ?string $environment = null)
    {
        $this->readConfig($filename);
        $this->doDeploy($environment);
    }

    public function build(string $filename)
    {
        $this->readConfig($filename);
        $this->doBuild();
    }

    private function readConfig(string $filename)
    {
        $this->config = Yaml::parseFile($filename);
        //dump($this->config);
    }

    private function doBuild()
    {
        $this->output->writeln([
            '<bg=blue;fg=white>              </>',
            '<bg=blue;fg=white> Build Phase  </>',
            '<bg=blue;fg=white>              </>',
        ]);
        foreach ($this->config['build'] ?? [] as $b) {
            $type = $b['type'] ?? null;
            if (!$type) {
                continue;
            }
            if ('docker' === $type) {
                $this->docker($b);
                continue;
            }
            if ('docker_push' === $type) {
                $this->dockerPush($b);
                continue;
            }
        }
    }

    private function doDeploy(?string $environment = null)
    {
        $this->output->writeln([
            '<bg=blue;fg=white>              </>',
            '<bg=blue;fg=white> Deploy Phase </>',
            '<bg=blue;fg=white>              </>',
        ]);
        $commands = $this->config['deploy'];
        if ($this->hasEnvironments() && null !== $environment) {
            $commands = $this->config['deploy'][$environment] ?? [];
        }
        $this->runDeploy($commands);
    }

    private function runDeploy(array $commands)
    {
        foreach ($commands as $c) {
            $type = $c['type'] ?? null;
            if (!$type) {
                continue;
            }
            if ('levant' === $type) {
                $this->levant($c);
            }
            if ('nomad' === $type) {
                $this->nomad($c);
            }
        }
    }

    private function docker(array $config)
    {
        $name = $config['name'] ?? null;
        if (!$name) {
            throw new LogicException('invalid config file: docker');
        }
        $tag = $config['image'] ?? null;
        if (!$tag) {
            throw new LogicException('invalid config file: docker');
        }

        $directory = $config['path'] ?? null;
        $env = null;
        if (isset($config['buildkit']) && $config['buildkit']) {
            $env['DOCKER_BUILDKIT'] = 1;
        }
        $filename = $config['file'] ?? 'Dockerfile';
        $command = ['docker', 'build', '--tag', $tag, '-f', $filename, '.'];

        $this->execCmd($command, $directory, $env, null, null);
        $this->artifacts[$name] = $tag;
    }

    private function dockerPush(array $config)
    {
        $name = $config['name'] ?? null;
        if (!$name) {
            throw new LogicException('invalid config file: docker push');
        }
        $tag = $config['image'] ?? null;
        if (!$tag) {
            throw new LogicException('invalid config file: docker push');
        }

        $command = ['docker', 'push', $this->replaceArtifacts($tag)];

        $this->execCmd($command);
    }

    private function levant(array $config)
    {
        $template = $config['template'] ?? null;
        if (!$template) {
            throw new LogicException('invalid config file: levant');
        }

        $output = $config['output'] ?? null;
        if (!$output) {
            throw new LogicException('invalid config file: levant');
        }

        $variables = $config['variables'] ?? null;

        $command = [
            'render',
            null,
            '-out=' . $output,
            $template,
        ];
        if ($variables) {
            $command[1] = '-var-file=' . $variables;
        }

        $this->execCmd(array_merge(['levant'], array_filter($command)));
    }

    private function nomad(array $config)
    {
        $server = $config['server'] ?? null;

        $job = $config['jobspec'] ?? null;
        if (!$job) {
            throw new LogicException('invalid config file: nomad');
        }

        $env = null;
        if ($server) {
            $env = ['NOMAD_ADDR=' . $server];
        }
        $command = [
            'nomad',
            'job',
            'run',
            $job,
        ];

        $this->execCmd($command, null, $env, null, null);
    }

    private function execCmd(array $command, string $directory = null, array $env = null, $input = null, ?float $timeout = 60)
    {
        $this->output->writeln(implode(' ', $command), OutputInterface::VERBOSITY_DEBUG);
        $process = new Process($command, $directory, $env, $input, $timeout);
        $process->mustRun();
        $this->output->writeln($process->getOutput());
    }

    private function hasEnvironments(): bool
    {
        return array_keys($this->config['deploy']) !== range(0, count($this->config['deploy']) - 1);
    }

    private function replaceArtifacts(string $text): string
    {
        $pattern = '/\$artifact+\[[\'|\"]([\w]+)[\'|\"]\]/';
        $matches = [];
        preg_match_all($pattern, $text, $matches);
        if (2 === count($matches)) {
            foreach ($matches[0] as $i => $r) {
                $value = $this->artifacts[$matches[1][$i]] ?? '';
                $text = str_replace($r, $value, $text);
            }
        }

        return $text;
    }
}