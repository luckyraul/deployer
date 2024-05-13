<?php

namespace Mygento\Deployer\Model;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Workflow
{
    private OutputInterface $output;

    private array $config = [];

    private array $artifacts = [];

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
        $this->output->writeln(
            [
                '<bg=blue;fg=white>              </>',
                '<bg=blue;fg=white> Build Phase  </>',
                '<bg=blue;fg=white>              </>',
            ]
        );
        foreach ($this->config['build'] ?? [] as $b) {
            $type = $b['type'] ?? null;
            if (!$type) {
                continue;
            }
            if ('command' === $type) {
                $this->cmd($b);
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
        $this->output->writeln(
            [
                '<bg=blue;fg=white>              </>',
                '<bg=blue;fg=white> Deploy Phase </>',
                '<bg=blue;fg=white>              </>',
            ]
        );
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
            if ('command' === $type) {
                $this->cmd($c);
                continue;
            }
            if ('levant' === $type) {
                $this->levant($c);
                continue;
            }
            if ('nomad' === $type) {
                $this->nomad($c);
                continue;
            }
            if ('nomad_pack' === $type) {
                $this->nomadPack($c);
                continue;
            }
        }
    }

    private function docker(array $config)
    {
        $name = $config['name'] ?? null;
        if (!$name) {
            throw new LogicException('invalid config file: docker');
        }
        $tagN = $config['image'] ?? null;
        if (!$tagN) {
            throw new LogicException('invalid config file: docker');
        }
        $tag = $this->replaceVars($tagN);
        $directory = $config['path'] ?? null;
        $env = null;
        $buildkit = $config['buildkit'] ?? true;
        if ($buildkit) {
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

        $command = [
            'nomad',
            'job',
            'run',
            null,
            '-verbose',
            $job,
        ];

        if ($server) {
            $command[3] = '--address=' . $server;
        }

        $this->execCmd(array_filter($command));
    }

    private function nomadPack(array $config)
    {
        $server = $config['server'] ?? null;
        $registry = $config['registry'] ?? null;
        $registryUrl = $config['registry_url'] ?? null;
        $variables = $config['variables'] ?? [];

        $task = $config['task'] ?? null;
        if (!$task) {
            throw new LogicException('invalid config file: nomad pack');
        }

        if ($registry && $registryUrl) {
            $registryCommand = [
                'nomad-pack',
                'registry',
                'add',
                $registry,
                $registryUrl,
            ];
            $this->execCmd($registryCommand, null, null, null, 0);
        }

        $command = [
            'nomad-pack',
            'run',
            $task,
            null,
            null,
            '--ref=latest',
            '--parser-v1',
        ];

        if ($server) {
            $command[3] = '--address=' . $server;
        }
        if ($registry) {
            $command[4] = '--registry=' . $registry;
        }
        foreach ($variables as $v) {
            $command[] = '--var';
            $command[] = $v;
        }
        $this->execCmd(array_filter($command), null, null, null, 0);
    }

    private function cmd(array $config)
    {
        $command = $config['command'] ?? null;
        if (!$command) {
            throw new LogicException('invalid config file: command');
        }
        $directory = $config['directory'] ?? null;
        if (!is_array($command)) {
            $command = [$command];
        }
        $this->execCmd($command, $directory, null, null, 0);
    }

    private function execCmd(array $command, string $directory = null, array $env = null, $input = null, ?float $timeout = 120)
    {
        $this->output->writeln('<bg=green;fg=white>' . implode(' ', $command) . '</>', OutputInterface::VERBOSITY_DEBUG);
        $process = new Process($command, $directory, $env, $input, $timeout);
        $process->mustRun(function ($type, $buffer) {
            $this->output->writeln($buffer);
        });
        $this->output->writeln('<question>Exit code: ' . $process->getExitCode() . '</question>');
    }

    private function hasEnvironments(): bool
    {
        return array_keys($this->config['deploy']) !== range(0, count($this->config['deploy']) - 1);
    }

    private function getGitBranch(): ?string
    {
        $gitStr = file_get_contents('.git/HEAD');
        if (!$gitStr) {
            return null;
        }

        return str_replace(['ref: refs/heads/', "\n"], '', $gitStr);
    }

    private function replaceVars(string $text): string
    {
        return str_replace('$git[\'branch\']', $this->getGitBranch(), $text);
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
