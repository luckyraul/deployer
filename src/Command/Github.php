<?php

namespace Mygento\Deployer\Command;

use GuzzleHttp\Exception\GuzzleException;
use Mygento\Deployer\Model\GithubApi;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'github-release')]
class Github extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('github-release')
            ->setDescription('Fetch Github release')
            ->setDefinition(
                [
                    new InputArgument('repo', InputArgument::REQUIRED, 'repo'),
                    new InputOption('extension', null, InputOption::VALUE_OPTIONAL, 'release extension', null),
                    new InputOption('arch', null, InputOption::VALUE_OPTIONAL, 'release arch', null),
                ],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $input->getArgument('repo');
        $ext = $input->getOption('extension');
        $arch = $input->getOption('arch');

        if (!preg_match('#^[^/]+/[^/]+$#', $repo)) {
            $output->writeln('<error>Repository must be in owner/repo format.</error>');

            return Command::FAILURE;
        }
        $executor = new GithubApi();
        $release = [];
        $architectures = array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    strtolower($arch ?? ''),
                ),
            ),
        );

        try {
            $release = $executor->findRelease($repo);
            $toDownload = $this->filter($release, $architectures, $ext);
            $progressBar = new ProgressBar($output, count($toDownload));
            $progressBar->start();
            foreach ($toDownload as $url) {
                $filename = basename(
                    parse_url($url, PHP_URL_PATH),
                );
                $progressBar->setMessage('Downloading ' . $filename);
                $executor->download($url);
                $progressBar->advance();
            }
        } catch (GuzzleException $e) {
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    $e->getMessage(),
                ),
            );

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    $e->getMessage(),
                ),
            );

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function filter(array $release, array $architectures, ?string $ext = null): array
    {
        return array_filter(
            $release,
            function (string $assetUrl) use ($ext, $architectures): bool {
                if (
                    $ext && false === strpos(
                        $assetUrl,
                        strtolower('.' . $ext),
                    )
                ) {
                    return false;
                }
                if (count($architectures) > 0) {
                    $matched = false;

                    foreach (
                        $architectures as $architecture
                    ) {
                        if (
                            false !== strpos(
                                $assetUrl,
                                $architecture,
                            )
                        ) {
                            $matched = true;
                            break;
                        }
                    }

                    if (!$matched) {
                        return false;
                    }
                }

                return true;
            },
        );
    }
}
