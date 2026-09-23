<?php

namespace Mygento\Deployer\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Jumbojett\OpenIDConnectClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Jumbojett\base64url_decode;

class Upload extends Command
{
    private const TYPES = [
        'private_apt',
        'public_apt',
    ];
    private const DEFAULT_CONCURRENCY = 4;

    protected function configure(): void
    {
        $this
            ->setName('upload')
            ->setDescription('Upload artifacts')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'Artifact type',
            )
            ->addArgument(
                'files',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Artifact files',
            )
            ->addOption(
                'distro',
                null,
                InputOption::VALUE_OPTIONAL,
                'APT distro',
            )
            ->addOption(
                'batchName',
                null,
                InputOption::VALUE_OPTIONAL,
                'Upload batch name',
            )
            ->addOption(
                'concurrency',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Number of parallel uploads',
                self::DEFAULT_CONCURRENCY,
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $type = $input->getArgument('type');

        if (!in_array($type, self::TYPES, true)) {
            $output->writeln(
                sprintf(
                    '<error>Invalid artifact type: %s</error>',
                    $type,
                ),
            );

            return Command::FAILURE;
        }

        $service = getenv('SERVICE');
        if (!$service) {
            $output->writeln(
                '<error>SERVICE is not configured.</error>',
            );

            return Command::FAILURE;
        }

        $files = $this->parseFiles(
            $input->getArgument('files'),
        );

        if (!$files) {
            $output->writeln(
                '<error>No files specified.</error>',
            );

            return Command::FAILURE;
        }

        $concurrency = max(
            1,
            (int) $input->getOption('concurrency'),
        );

        $distro = $input->getOption('distro');
        $batchName = $input->getOption('batchName');

        if (null === $batchName || '' === trim($batchName)) {
            $batchName = $this->generateBatchName();
        } else {
            $batchName = trim($batchName);

            if (!$this->isValidBatchName($batchName)) {
                $output->writeln(
                    '<error>Invalid batchName. Use only letters, numbers, ".", "_" and "-".</error>',
                );

                return Command::FAILURE;
            }
        }

        $visibility = $this->getVisibility($type);

        $url = sprintf(
            '/repository/upload/apt/%s/%s',
            $visibility,
            rawurlencode($batchName),
        );
        $releaseUrl = $url . '/release';

        $output->writeln(sprintf('<info>Batch: %s</info>', $batchName));
        $output->writeln(sprintf('<info>Visibility: %s</info>', $visibility));
        $output->writeln(sprintf('<info>Concurrency: %d</info>', $concurrency));

        $validFiles = [];

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                $output->writeln(
                    sprintf(
                        '<error>File not found or unreadable: %s</error>',
                        $file,
                    ),
                );

                continue;
            }

            $validFiles[] = $file;
        }

        if (!$validFiles) {
            return Command::FAILURE;
        }

        $token = $this->getToken([$type]);

        if (!$token) {
            $output->writeln(
                '<error>Unable to obtain access token.</error>',
            );

            return Command::FAILURE;
        }

        if ($this->isTokenExpired($token)) {
            $token = $this->getToken([$type]);

            if (!$token) {
                $output->writeln(
                    '<error>Unable to refresh access token.</error>',
                );

                return Command::FAILURE;
            }
        }

        $client = new Client([
            'base_uri' => $service,
        ]);

        $jobs = [];

        foreach ($validFiles as $file) {
            $filename = basename($file);
            $section = $output->section();
            $progressBar = new ProgressBar(
                $section,
                filesize($file) ?: 0,
            );
            $progressBar->setFormat(
                sprintf(
                    ' %s [%s] %3s%%',
                    $filename,
                    '%bar%',
                    '%percent',
                ),
            );

            $progressBar->setBarCharacter('<fg=green>█</>');
            $progressBar->setEmptyBarCharacter('<fg=gray>░</>');
            $progressBar->setProgressCharacter('>');
            $progressBar->start();

            $jobs[] = [
                'file' => $file,
                'filename' => $filename,
                'progress' => $progressBar,
                'resource' => null,
                'success' => false,
            ];
        }

        /*
         * Generate asynchronous requests.
         */
        $requests = function () use (
            &$jobs,
            $client,
            $url,
            $token,
        ) {
            foreach ($jobs as $index => &$job) {
                $resource = fopen($job['file'], 'rb');

                if (false === $resource) {
                    yield $index => function () use ($job): void {
                        throw new \RuntimeException(sprintf('Unable to open file: %s', $job['file']));
                    };

                    continue;
                }

                $job['resource'] = $resource;

                $query = http_build_query(
                    array_filter(
                        [
                            'filename' => $job['filename'],
                        ],
                        static fn($value) => '' !== $value,
                    ),
                );

                yield $index => function () use (
                    $client,
                    $url,
                    $query,
                    $token,
                    &$job,
                ) {
                    return $client->requestAsync(
                        'POST',
                        $url . '?' . $query,
                        [
                            'body' => $job['resource'],

                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => 'Bearer ' . $token,
                            ],

                            'progress' => function (
                                int $downloadTotal,
                                int $downloaded,
                                int $uploadTotal,
                                int $uploaded,
                            ) use (&$job): void {
                                if ($uploadTotal <= 0) {
                                    return;
                                }

                                $job['progress']->setMaxSteps(
                                    $uploadTotal,
                                );

                                $job['progress']->setProgress(
                                    $uploaded,
                                );
                            },
                        ],
                    );
                };
            }

            unset($job);
        };

        $failed = false;
        $pool = new Pool(
            $client,
            $requests(),
            [
                'concurrency' => $concurrency,
                'fulfilled' => function (
                    $response,
                    $index,
                ) use (
                    &$jobs,
                    $output,
                    $service,
                    $url,
                ): void {
                    $job = &$jobs[$index];

                    $job['success'] = true;

                    $job['progress']->finish();

                    if (is_resource($job['resource'])) {
                        fclose($job['resource']);
                        $job['resource'] = null;
                    }

                    $output->writeln(
                        sprintf(
                            '<info>Uploaded %s to %s%s</info>',
                            $job['filename'],
                            $service,
                            $url,
                        ),
                    );
                },

                'rejected' => function (
                    $reason,
                    $index,
                ) use (
                    &$jobs,
                    $output,
                    $service,
                    $url,
                    &$failed,
                ): void {
                    $job = &$jobs[$index];

                    $failed = true;

                    $job['progress']->finish();

                    if (is_resource($job['resource'])) {
                        fclose($job['resource']);
                        $job['resource'] = null;
                    }

                    $message = $this->getExceptionMessage($reason);

                    $output->writeln(
                        sprintf(
                            '<error>Failed to upload %s to %s%s: %s</error>',
                            $job['filename'],
                            $service,
                            $url,
                            $message,
                        ),
                    );
                },
            ],
        );

        try {
            $pool->promise()->wait();
        } catch (\Throwable $e) {
            $failed = true;

            $output->writeln(
                sprintf(
                    '<error>Upload pool failed: %s</error>',
                    $e->getMessage(),
                ),
            );
        }

        foreach ($jobs as &$job) {
            if (is_resource($job['resource'])) {
                fclose($job['resource']);
                $job['resource'] = null;
            }
        }

        unset($job);

        if ($failed) {
            $output->writeln('');
            $output->writeln(
                sprintf(
                    '<error>Batch "%s" was NOT released because one or more uploads failed.</error>',
                    $batchName,
                ),
            );

            return Command::FAILURE;
        }

        /*
         * All uploads succeeded.
         *
         * Now commit/release the batch.
         */
        $output->writeln('');
        $output->writeln(
            sprintf(
                '<info>All uploads completed. Releasing batch "%s"...</info>',
                $batchName,
            ),
        );

        try {
            $client->request(
                'POST',
                $releaseUrl,
                [
                    'query' => $distro ? ['dist' => $distro] : [],
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ],
            );

            $output->writeln(
                sprintf(
                    '<info>Batch "%s" released successfully%s.</info>',
                    $batchName,
                    $distro ? sprintf(' for dist "%s"', $distro) : '',
                ),
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf(
                    '<error>Failed to release batch "%s": %s</error>',
                    $batchName,
                    $this->getExceptionMessage($e),
                ),
            );

            return Command::FAILURE;
        }
    }

    /**
     * Supports:
     *
     *   file1.deb file2.deb
     *   file1.deb,file2.deb
     *   file1.deb, file2.deb
     *
     * as well as normal Symfony array arguments:
     *
     *   upload type file1.deb file2.deb
     *
     * @param array<int, string> $files
     *
     * @return array<int, string>
     */
    private function parseFiles(array $files): array
    {
        $result = [];

        foreach ($files as $value) {
            $value = trim($value);

            if ('' === $value) {
                continue;
            }

            $parts = preg_split(
                '/[\s,]+/',
                $value,
                -1,
                PREG_SPLIT_NO_EMPTY,
            );

            if (false === $parts) {
                continue;
            }

            foreach ($parts as $file) {
                $file = trim($file);

                if ('' !== $file) {
                    $result[] = $file;
                }
            }
        }

        return array_values(array_unique($result));
    }

    private function getVisibility(string $type): string
    {
        switch ($type) {
            case 'private_apt':
                return 'private';
            case 'public_apt':
                return 'public';
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported artifact type: %s', $type));
        }
    }

    private function generateBatchName(): string
    {
        return sprintf(
            '%s-%s',
            date('Ymd-His'),
            bin2hex(random_bytes(4)),
        );
    }

    private function isValidBatchName(string $batchName): bool
    {
        return 1 === preg_match(
            '/^[a-zA-Z0-9._-]+$/',
            $batchName,
        );
    }

    /**
     * @param array<int, string> $scopes
     */
    private function getToken(array $scopes): ?string
    {
        $login = getenv('REALM_LOGIN');
        $password = getenv('REALM_PASS');
        $realm = getenv('REALM');

        if (!$login || !$password || !$realm) {
            return null;
        }

        try {
            $oidc = new OpenIDConnectClient(
                $realm,
                $login,
                $password,
            );

            $oidc->addScope($scopes);

            $result = $oidc->requestClientCredentialsToken();

            if (!$result || !isset($result->access_token)) {
                return null;
            }

            return $result->access_token;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isTokenExpired(string $jwt): bool
    {
        $payload = $this->decodeJwtPayload($jwt);

        if (
            !isset($payload['exp'])
            || !is_numeric($payload['exp'])
        ) {
            return true;
        }

        /*
         * Give the batch a 30 second safety margin.
         */
        return (int) $payload['exp'] <= time() + 30;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (3 !== count($parts)) {
            return [];
        }

        try {
            $payload = base64url_decode($parts[1]);
            $decoded = json_decode($payload, true);
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($decoded)
            ? $decoded
            : [];
    }

    private function getExceptionMessage($reason): string
    {
        if ($reason instanceof RequestException && $reason->hasResponse()) {
            return sprintf(
                'HTTP %d: %s',
                $reason->getResponse()->getStatusCode(),
                $reason->getMessage(),
            );
        }

        if ($reason instanceof \Throwable) {
            return $reason->getMessage();
        }

        return is_string($reason) ? $reason : 'Unknown error';
    }
}
