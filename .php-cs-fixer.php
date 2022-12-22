<?php
$finder = PhpCsFixer\Finder::create()->in('./src');
$config = new \Mygento\Symfony\Config\Symfony();
$config->setFinder($finder);
return $config;
