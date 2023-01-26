#!/usr/bin/env php

<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Jumbojett\OpenIDConnectClient;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$home = getenv('HOME');
$globalAutoloadFile = $home . '/.composer/vendor/autoload.php';
$globalAutoloadFile2 = $home . '/.config/composer/vendor/autoload.php';
$rootAutoloadFile = '/root/.composer/vendor/autoload.php';
$rootAutoloadFile2 = '/root/.config/composer/vendor/autoload.php';

if (file_exists('vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} elseif (file_exists($globalAutoloadFile)) {
    require_once $globalAutoloadFile;
} elseif (file_exists($globalAutoloadFile2)) {
    require_once $globalAutoloadFile2;
} elseif (file_exists($rootAutoloadFile)) {
    require_once $rootAutoloadFile;
} elseif (file_exists($rootAutoloadFile2)) {
    require_once $rootAutoloadFile2;
}

class UploadPackageCommand extends Command
{
    protected static $defaultName = 'upload';

    protected function configure()
    {
        $this->setDescription('Upload atrifacts');
        $this->addArgument('type', InputArgument::REQUIRED, 'Artifact type');
        $this->addArgument(
            'files',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'Artifact files'
        );
        $this->addOption(
            'distro',
            null,
            InputOption::VALUE_OPTIONAL,
            'Apt distro'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        $dist = false;
        switch ($type) {
            case 'private_apt':
            case 'public_apt':
                $scope = [$type];
                $files = $input->getArgument('files');
                $dist = $input->getOption('distro');
                $url = '/repository/upload/' . $scope[0];
                break;
            default:
                $output->writeln('invalid type');
                return 1;
        }

        $token = $this->getToken($scope);

        if (!$token) {
            $output->writeln('Token Invalid');
            return 1;
        }

        $service = getenv('SERVICE');
        if (!$service) {
            $output->writeln('Service Invalid');
            return 1;
        }

        foreach ($files as $file) {
            if (!$file || !file_exists($file)) {
                $output->writeln('file not found ' . $file);
                continue;
            }

            $client = new Client([
                'base_uri' => $service,
            ]);

            $filename = basename($file);
            $body = fopen($file, 'r');
            $query = '?' . http_build_query(
                array_merge(
                        [
                            'filename' => $filename,
                        ],
                        $dist ? [
                        'dist' => $dist,
                        ] : []
                    )
            );

            try {
                $client->request('POST', $url . $query, [
                    'body' => $body,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);
                $output->writeln('uploaded ' . $filename . ' to ' . $service . $url);
            } catch (ClientException $e) {
                $output->writeln(
                    $service . $url
                    . ' invalid http response: ' .
                    $e->getResponse()->getStatusCode()
                );
                continue;
            }
        }

        return 0;
    }

    private function getToken(array $scopes)
    {
        $login = getenv('REALM_LOGIN');
        $pass = getenv('REALM_PASS');
        $realm = getenv('REALM');

        $oidc = new OpenIDConnectClient($realm, $login, $pass);
        foreach ($scopes as $sc) {
            $oidc->addScope($sc);
        }
        $result = $oidc->requestClientCredentialsToken();
        if (!$result || !isset($result->access_token)) {
            return false;
        }
        return $result->access_token;
    }
}

$application = new Application();
$application->add(new UploadPackageCommand());
$application->run();
