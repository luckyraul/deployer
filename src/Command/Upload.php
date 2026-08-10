<?php

namespace Mygento\Deployer\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Jumbojett\OpenIDConnectClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Jumbojett\base64url_decode;

#[AsCommand(name: 'upload')]
class Upload extends Command
{
    protected function configure(): void
    {
        $this->setName('upload');
        $this->setDescription('Upload atrifacts');
        $this->addArgument('type', InputArgument::REQUIRED, 'Artifact type');
        $this->addArgument(
            'files',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'Artifact files',
        );
        $this->addOption(
            'distro',
            null,
            InputOption::VALUE_OPTIONAL,
            'Apt distro',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $dist = false;
        switch ($type) {
            case 'private_apt':
            case 'public_apt':
                $scope = [$type];
                $files = array_merge(
                    ...array_map(
                        fn($t) => array_map(
                            'trim',
                            explode(',', $t),
                        ),
                        array_map(
                            'trim',
                            array_filter(
                                $input->getArgument('files') ?? [],
                            ),
                        ),
                    ),
                );
                $dist = $input->getOption('distro');
                $url = '/repository/upload/' . $scope[0];
                break;
            default:
                $output->writeln('invalid type');

                return Command::FAILURE;
        }

        $token = $this->getToken($scope);

        if (!$token) {
            $output->writeln('Token Invalid');

            return Command::FAILURE;
        }

        $service = getenv('SERVICE');
        if (!$service) {
            $output->writeln('Service Invalid');

            return Command::FAILURE;
        }
        $progressBar = new ProgressBar($output, 0);
        $progressBar->setFormat(
            ' %message% [%bar%] %percent:3s%%',
        );

        foreach ($files as $file) {
            if (!$file || !file_exists($file)) {
                $output->writeln('file not found ' . $file);
                continue;
            }

            if ($this->validateJWTForExpiry($token)) {
                $token = $this->getToken($scope);
            }
            $filename = basename($file);

            $progressBar->setMessage(sprintf('Uploading %s ', $filename));
            $progressBar->setMaxSteps(0);
            $progressBar->start();

            $client = new Client(
                [
                    'base_uri' => $service,
                ],
            );

            $body = fopen($file, 'r');
            $query = '?' . http_build_query(
                array_merge(
                    [
                        'filename' => $filename,
                    ],
                    $dist ? [
                        'dist' => $dist,
                    ] : [],
                ),
            );

            try {
                $client->request(
                    'POST',
                    $url . $query,
                    [
                        'body' => $body,
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $token,
                        ],
                        'progress' => function (
                            int $downloadTotal,
                            int $downloaded,
                            int $uploadTotal,
                            int $uploaded,
                        ) use ($progressBar): void {
                            if ($uploadTotal > 0) {
                                $progressBar->setMaxSteps($uploadTotal);
                                $progressBar->setProgress($uploaded);
                            }
                        },
                    ],
                );
                $output->writeln('');
                $output->writeln('uploaded ' . $filename . ' to ' . $service . $url);
            } catch (ServerException $e) {
                $output->writeln(
                    $service . $url
                        . ' invalid http response: '
                        . $e->getResponse()->getStatusCode(),
                );
                continue;
            } catch (ClientException $e) {
                $output->writeln(
                    $service . $url
                        . ' invalid http response: '
                        . $e->getResponse()->getStatusCode(),
                );
                continue;
            }
            $progressBar->finish();
        }

        return Command::SUCCESS;
    }

    private function getToken(array $scopes): ?string
    {
        $login = getenv('REALM_LOGIN');
        $pass = getenv('REALM_PASS');
        $realm = getenv('REALM');

        $oidc = new OpenIDConnectClient($realm, $login, $pass);
        $oidc->addScope($scopes);
        $result = $oidc->requestClientCredentialsToken();
        if (!$result || !isset($result->access_token)) {
            return null;
        }

        return $result->access_token;
    }

    private function decodeJWT(string $jwt, $section = 1): array
    {
        $parts = explode('.', $jwt);

        return json_decode(base64url_decode($parts[$section]), true);
    }

    private function validateJWTForExpiry(string $jwt): bool
    {
        $parts = $this->decodeJWT($jwt);

        return $parts['exp'] <= time();
    }
}
